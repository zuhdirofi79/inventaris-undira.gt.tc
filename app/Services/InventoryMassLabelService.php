<?php

namespace App\Services;

use App\Models\InventoryItem;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mass QR label PDF (A4 portrait, configurable grid).
 * Does not replace individual label print or legacy inventory.labels.
 */
class InventoryMassLabelService
{
    public function __construct(
        protected InventoryFilterService $filterService,
    ) {}

    /**
     * @return array{
     *     columns: int,
     *     rows: int,
     *     per_page: int,
     *     page_margin_mm: float,
     *     gap_x_mm: float,
     *     gap_y_mm: float,
     *     cell_pad_x_mm: float,
     *     cell_pad_y_mm: float,
     *     qr_size_mm: float,
     *     logo_width_mm: float,
     *     logo_max_height_mm: float,
     *     label_padding_mm: float,
     *     label_padding_bottom_mm: float,
     *     number_row_mm: float,
     *     qr_col_width_pct: float,
     *     font_size_pt: float,
     *     name_max_chars: int,
     *     max_items: int,
     *     cell_width_pct: float,
     *     cell_height_mm: float,
     *     cell_width_mm: float,
     *     label_height_mm: float,
     *     label_width_mm: float
     * }
     */
    public function layout(): array
    {
        $columns = max(1, (int) config('inventory_labels.columns', 5));
        $rows = max(1, (int) config('inventory_labels.rows', 8));
        $pageMargin = max(4.0, (float) config('inventory_labels.page_margin_mm', 5));
        $gapX = max(4.0, min(8.0, (float) config('inventory_labels.gap_x_mm', 5)));
        $gapY = max(4.0, min(8.0, (float) config('inventory_labels.gap_y_mm', 5)));
        // QR size is locked — never shrink for gutters.
        $qrSize = max(18.0, min(22.0, (float) config('inventory_labels.qr_size_mm', 20)));
        $logoWidth = max(11.0, min(18.0, (float) config('inventory_labels.logo_width_mm', 14)));
        $logoMaxHeight = max(3.2, min(6.0, (float) config('inventory_labels.logo_max_height_mm', 4.5)));
        $labelPadding = max(0.8, min(2.5, (float) config('inventory_labels.label_padding_mm', 1.0)));
        $labelPaddingBottom = max(2.0, min(5.0, (float) config('inventory_labels.label_padding_bottom_mm', 2.5)));
        $fontPt = max(7.0, min(10.0, (float) config('inventory_labels.font_size_pt', 7.5)));
        $numberRowMm = 2.8;

        /*
         * DomPDF-safe grid: divide the full usable area into equal cells.
         * Gaps are cell padding (NOT border-spacing). That keeps total table
         * height = usable height so DomPDF does not page-break mid-grid.
         */
        $usableWidth = 210.0 - (2 * $pageMargin);
        $usableHeight = 297.0 - (2 * $pageMargin);
        $cellWidth = $usableWidth / $columns;
        $cellHeight = $usableHeight / $rows;

        // Ease gaps only if the label box cannot hold the locked QR.
        $minGap = 4.0;
        while ($gapY > $minGap + 0.001) {
            $labelHeight = $cellHeight - $gapY;
            $minLabelHeight = $qrSize + $labelPadding + $labelPaddingBottom + 0.3;
            if ($labelHeight + 0.001 >= $minLabelHeight) {
                break;
            }
            $gapY = round($gapY - 0.25, 2);
        }

        $cellPadX = $gapX / 2;
        $cellPadY = $gapY / 2;
        $labelWidth = max(1.0, $cellWidth - $gapX);
        $labelHeight = max(1.0, $cellHeight - $gapY);

        // QR column hugs the QR square inside the label box.
        $qrColPct = min(58.0, max(42.0, (($qrSize + 0.5) / max(1.0, $labelWidth)) * 100));

        return [
            'columns' => $columns,
            'rows' => $rows,
            'per_page' => $columns * $rows,
            'page_margin_mm' => $pageMargin,
            'gap_x_mm' => round($gapX, 2),
            'gap_y_mm' => round($gapY, 2),
            'cell_pad_x_mm' => round($cellPadX, 2),
            'cell_pad_y_mm' => round($cellPadY, 2),
            'qr_size_mm' => $qrSize,
            'logo_width_mm' => $logoWidth,
            'logo_max_height_mm' => $logoMaxHeight,
            'label_padding_mm' => $labelPadding,
            'label_padding_bottom_mm' => $labelPaddingBottom,
            'number_row_mm' => $numberRowMm,
            'qr_col_width_pct' => round($qrColPct, 2),
            'font_size_pt' => $fontPt,
            'name_max_chars' => max(20, (int) config('inventory_labels.name_max_chars', 32)),
            'max_items' => max(1, (int) config('inventory_labels.max_items', 500)),
            'cell_width_pct' => 100 / $columns,
            'cell_height_mm' => round($cellHeight, 2),
            'cell_width_mm' => round($cellWidth, 2),
            'label_height_mm' => round($labelHeight, 2),
            'label_width_mm' => round($labelWidth, 2),
        ];
    }

    /**
     * Resolve printable inventory IDs from selected / filter scope.
     *
     * @return list<int>
     */
    public function resolveIds(Request $request): array
    {
        $scope = (string) $request->input('scope');

        if ($scope === 'selected') {
            return collect($request->input('inventory_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $layout = $this->layout();

        return $this->filterService
            ->apply($request)
            ->orderBy('inventory_number')
            ->limit($layout['max_items'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Build label rows ready for the PDF view (skips invalid / QR failures).
     *
     * QR is rendered in-memory as PNG for DomPDF — no per-item disk ensureCodes
     * (that was slow enough to hold the request open and starve DataTables AJAX).
     *
     * @param  list<int>  $ids
     * @return Collection<int, object{
     *     inventory_number: string,
     *     name: string,
     *     category: string,
     *     room: string,
     *     location_lines: list<string>,
     *     qr_src: string
     * }>
     */
    public function buildLabels(array $ids): Collection
    {
        $layout = $this->layout();
        $labels = collect();

        if ($ids === []) {
            return $labels;
        }

        $items = InventoryItem::query()
            ->whereIn('id', $ids)
            ->whereNotNull('inventory_number')
            ->where('inventory_number', '!=', '')
            ->with([
                'category:id,name',
                'room:id,code,name,floor_id',
                'room.floor:id,code,name,floor_number,building_id',
                'room.floor.building:id,code,name',
            ])
            ->orderBy('inventory_number')
            ->get();

        // Sharper raster for ~20mm print size (~300 dpi equivalent).
        $qrPixels = max(220, (int) round($layout['qr_size_mm'] * 12));

        foreach ($items as $item) {
            $number = trim((string) $item->inventory_number);
            if ($number === '') {
                Log::info('Mass label skip: empty inventory number', ['id' => $item->id]);

                continue;
            }

            $qrSrc = $this->qrPngDataUri($number, $qrPixels);
            if ($qrSrc === null) {
                Log::warning('Mass label skip: QR PNG failed', [
                    'id' => $item->id,
                    'inventory_number' => $number,
                ]);

                continue;
            }

            $category = trim((string) ($item->category?->name ?? ''));
            $room = $this->formatRoomLabel($item);

            $labels->push((object) [
                'inventory_number' => $number,
                'name' => $this->formatName((string) $item->name, $layout['name_max_chars']),
                'category' => $category !== '' ? Str::limit($category, 28, '...') : '—',
                'room' => $room,
                'location_lines' => [$room],
                'qr_src' => $qrSrc,
            ]);
        }

        return $labels;
    }

    public function downloadPdf(Collection $labels): Response
    {
        $layout = $this->layout();
        $logo = $this->resolveLogo();

        if ($logo['src'] === null) {
            Log::warning('Mass label PDF: logo-undira.png tidak ditemukan', [
                'tried' => $logo['tried'],
                'error' => $logo['error'],
            ]);
        } else {
            Log::info('Mass label PDF: logo loaded', [
                'path' => $logo['path'],
            ]);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf = Pdf::loadView('exports.inventory-labels-30', [
            'labels' => $labels,
            'layout' => $layout,
            'logoSrc' => $logo['src'],
            'logoPath' => $logo['path'],
            'logoError' => $logo['error'],
            'logoTried' => $logo['tried'],
        ]);
        $pdf->setPaper('a4', 'portrait');

        $perPage = $layout['per_page'];
        $filename = 'inventory_labels_'.$perPage.'_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Embed official logo-undira.png as base64 data-URI for DomPDF.
     * Uses public_path() only — never asset()/HTTP URLs.
     *
     * @deprecated Prefer resolveLogo() when failure details are needed.
     */
    public function resolveLogoDataUri(): ?string
    {
        return $this->resolveLogo()['src'];
    }

    /**
     * @return array{
     *     src: ?string,
     *     path: ?string,
     *     tried: list<array{path: string, reason: string}>,
     *     error: ?string
     * }
     */
    public function resolveLogo(): array
    {
        $relative = ltrim(str_replace('\\', '/', (string) config('inventory_labels.logo_path', 'images/logo-undira.png')), '/');

        // Official file name must remain logo-undira.png — do not invent alternate names.
        $candidates = [
            public_path('images/logo-undira.png'),
            public_path($relative),
            base_path('public/images/logo-undira.png'),
            base_path('public/'.$relative),
        ];

        $tried = [];

        foreach (array_values(array_unique($candidates)) as $path) {
            if (! file_exists($path)) {
                $tried[] = ['path' => $path, 'reason' => 'file does not exist'];

                continue;
            }

            if (! is_file($path)) {
                $tried[] = ['path' => $path, 'reason' => 'path exists but is not a file'];

                continue;
            }

            if (! is_readable($path)) {
                $tried[] = ['path' => $path, 'reason' => 'file is not readable'];

                continue;
            }

            $binary = file_get_contents($path);
            if ($binary === false || $binary === '') {
                $tried[] = ['path' => $path, 'reason' => 'file is empty or unreadable'];

                continue;
            }

            $mime = 'image/png';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $mime = 'image/jpeg';
            } elseif ($ext === 'webp') {
                $mime = 'image/webp';
            }

            return [
                'src' => 'data:'.$mime.';base64,'.base64_encode($binary),
                'path' => $path,
                'tried' => $tried,
                'error' => null,
            ];
        }

        $lines = collect($tried)
            ->map(fn (array $row) => $row['path'].' ('.$row['reason'].')')
            ->implode('; ');

        return [
            'src' => null,
            'path' => null,
            'tried' => $tried,
            'error' => 'logo-undira.png tidak ditemukan. Path dicoba: '.$lines,
        ];
    }

    /**
     * PNG data-URI for DomPDF (scannable; does not require Imagick).
     */
    public function qrPngDataUri(string $payload, int $pixelSize = 240): ?string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        try {
            $qrCode = Encoder::encode($payload, ErrorCorrectionLevel::M());
            $matrix = $qrCode->getMatrix();
            $moduleCount = $matrix->getWidth();
            $quietZone = 3; // print quiet zone for reliable phone scanning
            $modules = $moduleCount + (2 * $quietZone);
            $scale = max(1, (int) floor($pixelSize / $modules));
            $imageSize = $modules * $scale;

            $image = imagecreatetruecolor($imageSize, $imageSize);
            if ($image === false) {
                return null;
            }

            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            if ($white === false || $black === false) {
                imagedestroy($image);

                return null;
            }

            imagefilledrectangle($image, 0, 0, $imageSize - 1, $imageSize - 1, $white);

            for ($y = 0; $y < $moduleCount; $y++) {
                for ($x = 0; $x < $moduleCount; $x++) {
                    if ($matrix->get($x, $y) === 1) {
                        $px = ($x + $quietZone) * $scale;
                        $py = ($y + $quietZone) * $scale;
                        imagefilledrectangle(
                            $image,
                            $px,
                            $py,
                            $px + $scale - 1,
                            $py + $scale - 1,
                            $black
                        );
                    }
                }
            }

            ob_start();
            imagepng($image);
            $png = ob_get_clean();
            imagedestroy($image);

            if ($png === false || $png === '') {
                return null;
            }

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable $e) {
            Log::warning('QR PNG render failed', [
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function formatName(string $name, int $maxChars): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '') {
            return '—';
        }

        return Str::limit($name, $maxChars, '...');
    }

    /**
     * Room-only label text (no building / floor) — room codes are unique in-system.
     */
    protected function formatRoomLabel(InventoryItem $item): string
    {
        $room = $item->room;
        $roomRaw = trim((string) ($room?->code ?: $room?->name ?: ''));

        if ($roomRaw === '') {
            return '—';
        }

        if (Str::startsWith(Str::lower($roomRaw), 'ruang')) {
            return Str::limit($roomRaw, 24, '...');
        }

        // LAB02 / A101 style codes stay readable with Ruang prefix when not already present.
        return Str::limit('Ruang '.$roomRaw, 24, '...');
    }

    /**
     * @return list<string>
     *
     * @deprecated Kept for backward compatibility; labels now use formatRoomLabel().
     */
    protected function locationLines(InventoryItem $item): array
    {
        return [$this->formatRoomLabel($item)];
    }
}
