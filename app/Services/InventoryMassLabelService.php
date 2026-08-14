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
        $rows = max(1, (int) config('inventory_labels.rows', 9));
        // Tight but printable margins — required to fit 5×9 on A4 in DomPDF.
        $pageMargin = max(4.0, min(8.0, (float) config('inventory_labels.page_margin_mm', 5)));
        $gapX = max(1.2, min(4.0, (float) config('inventory_labels.gap_x_mm', 2)));
        $gapY = max(1.2, min(4.0, (float) config('inventory_labels.gap_y_mm', 1.8)));
        // Keep QR scannable but compact so text column + number row fit the cell.
        $qrSize = max(12.0, min(15.0, (float) config('inventory_labels.qr_size_mm', 13.5)));
        $logoWidth = max(7.0, min(12.0, (float) config('inventory_labels.logo_width_mm', 10)));
        $logoMaxHeight = max(2.6, min(4.0, (float) config('inventory_labels.logo_max_height_mm', 3.2)));
        $labelPadding = max(0.7, min(1.6, (float) config('inventory_labels.label_padding_mm', 1.0)));
        $labelPaddingBottom = max(0.5, min(1.5, (float) config('inventory_labels.label_padding_bottom_mm', 0.7)));
        $fontPt = max(5.0, min(8.0, (float) config('inventory_labels.font_size_pt', 6.0)));
        // Allow up to ~2 lines for long inventory numbers (no ellipsis / no clip).
        $numberRowMm = 4.6;

        /*
         * DomPDF-safe grid: divide the usable area into equal cells.
         * Gaps are cell padding (NOT border-spacing).
         *
         * DomPDF often ignores box-sizing:border-box, so CSS cell height is set to
         * LABEL height while padding supplies the cutting gap. Row pitch ≈
         * labelHeight + gapY. Small vertical slack prevents phantom blank pages.
         */
        $usableWidth = 210.0 - (2 * $pageMargin);
        $verticalSlack = 1.2;
        $usableHeight = max(1.0, 297.0 - (2 * $pageMargin) - $verticalSlack);
        $rowPitch = $usableHeight / $rows;
        $colPitch = $usableWidth / $columns;

        // Ease vertical gap only if the label box cannot hold QR + number row.
        $minGap = 1.2;
        while ($gapY > $minGap + 0.001) {
            $labelHeight = $rowPitch - $gapY;
            $minLabelHeight = $qrSize + $numberRowMm + $labelPadding + $labelPaddingBottom + 0.3;
            if ($labelHeight + 0.001 >= $minLabelHeight) {
                break;
            }
            $gapY = round($gapY - 0.15, 2);
        }

        // If still too tight, shrink QR slightly (never below 12mm).
        $labelHeight = max(1.0, $rowPitch - $gapY);
        $minLabelHeight = $qrSize + $numberRowMm + $labelPadding + $labelPaddingBottom + 0.3;
        while ($labelHeight + 0.001 < $minLabelHeight && $qrSize > 12.0) {
            $qrSize = round($qrSize - 0.25, 2);
            $minLabelHeight = $qrSize + $numberRowMm + $labelPadding + $labelPaddingBottom + 0.3;
        }

        $cellPadX = $gapX / 2;
        $cellPadY = $gapY / 2;
        $labelWidth = max(1.0, $colPitch - $gapX);
        $labelHeight = max(1.0, $rowPitch - $gapY);
        // CSS heights used by the Blade (DomPDF content-box friendly).
        $cellHeight = $labelHeight;
        $cellWidth = $colPitch;

        // QR column hugs the QR; leave most width for text (horizontal card).
        $qrColPct = min(46.0, max(32.0, (($qrSize + 0.5) / max(1.0, $labelWidth)) * 100));

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
            'name_max_chars' => max(18, (int) config('inventory_labels.name_max_chars', 48)),
            'max_items' => max(1, (int) config('inventory_labels.max_items', 500)),
            'cell_width_pct' => 100 / $columns,
            'cell_height_mm' => round($cellHeight, 2),
            'cell_width_mm' => round($cellWidth, 2),
            'row_pitch_mm' => round($rowPitch, 2),
            'label_height_mm' => round($labelHeight, 2),
            'label_width_mm' => round($labelWidth, 2),
            'label_inner_height_mm' => round(max(1.0, $labelHeight - $labelPadding - $labelPaddingBottom), 2),
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
     *     name_pt: float,
     *     name_max_height_pt: float,
     *     category: string,
     *     category_pt: float,
     *     room: string,
     *     room_pt: float,
     *     number_pt: float,
     *     number_letter_spacing: string,
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

        // Sharper raster for ~15mm print size (~300 dpi equivalent).
        $qrPixels = max(200, (int) round($layout['qr_size_mm'] * 14));

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
            $nameMeta = $this->nameTypography((string) $item->name, $layout['name_max_chars']);
            $categoryMeta = $this->singleLineTypography($category !== '' ? $category : '—', 26, 5.8, 5.2);
            $roomMeta = $this->singleLineTypography($room, 26, 5.8, 5.2);
            $numberMeta = $this->inventoryNumberTypography($number);

            if ($numberMeta['edge_case']) {
                Log::warning('Mass label edge case: inventory number may exceed physical label width at minimum font', [
                    'id' => $item->id,
                    'inventory_number' => $number,
                    'length' => mb_strlen($number),
                    'number_pt' => $numberMeta['pt'],
                ]);
            }

            $labels->push((object) [
                'inventory_number' => $number,
                'name' => $nameMeta['text'],
                'name_pt' => $nameMeta['pt'],
                'name_max_height_pt' => $nameMeta['max_height_pt'],
                'category' => $categoryMeta['text'],
                'category_pt' => $categoryMeta['pt'],
                'room' => $roomMeta['text'],
                'room_pt' => $roomMeta['pt'],
                'number_pt' => $numberMeta['pt'],
                'number_letter_spacing' => $numberMeta['letter_spacing'],
                'location_lines' => [$roomMeta['text']],
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
     * Render PDF bytes for verification / tooling (same view as download).
     */
    public function renderPdfBinary(Collection $labels): string
    {
        $layout = $this->layout();
        $logo = $this->resolveLogo();

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

        return $pdf->output();
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

    /**
     * Server-side name typography (no JS auto-fit). Prefer smaller font over ellipsis.
     *
     * @return array{text: string, pt: float, max_height_pt: float}
     */
    public function nameTypography(string $name, int $hardMaxChars = 48): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '') {
            return ['text' => '—', 'pt' => 7.0, 'max_height_pt' => 8.0];
        }

        $len = mb_strlen($name);

        // Extreme length: hard clamp without ellipsis (containment only).
        if ($len > $hardMaxChars) {
            $name = mb_substr($name, 0, $hardMaxChars);
            $len = mb_strlen($name);
        }

        // Keep max-height tight so DomPDF does not expand the QR-height text column.
        if ($len <= 16) {
            return ['text' => $name, 'pt' => 7.0, 'max_height_pt' => 8.0];
        }

        if ($len <= 26) {
            return ['text' => $name, 'pt' => 6.2, 'max_height_pt' => 13.5];
        }

        if ($len <= 36) {
            return ['text' => $name, 'pt' => 5.6, 'max_height_pt' => 12.5];
        }

        return ['text' => $name, 'pt' => 5.2, 'max_height_pt' => 12.0];
    }

    /**
     * Full inventory number — never ellipsis / never clip.
     * Prefer slight shrink; allow wrap to 2 lines for very long codes.
     *
     * @return array{pt: float, letter_spacing: string, edge_case: bool}
     */
    public function inventoryNumberTypography(string $number): array
    {
        $len = mb_strlen(trim($number));

        if ($len <= 22) {
            return ['pt' => 6.0, 'letter_spacing' => '-0.01em', 'edge_case' => false];
        }

        if ($len <= 26) {
            return ['pt' => 5.5, 'letter_spacing' => '-0.02em', 'edge_case' => false];
        }

        if ($len <= 30) {
            return ['pt' => 5.1, 'letter_spacing' => '-0.03em', 'edge_case' => false];
        }

        if ($len <= 36) {
            return ['pt' => 4.8, 'letter_spacing' => '-0.04em', 'edge_case' => false];
        }

        // Still render the full number (may wrap to 2 lines).
        return ['pt' => 4.5, 'letter_spacing' => '-0.05em', 'edge_case' => true];
    }

    /**
     * @return array{text: string, pt: float}
     */
    protected function singleLineTypography(string $text, int $softMax, float $normalPt, float $smallPt): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return ['text' => '—', 'pt' => $normalPt];
        }

        $len = mb_strlen($text);
        if ($len > $softMax) {
            // Category/room may soft-truncate; inventory number never uses this path.
            $text = Str::limit($text, $softMax, '…');
            $len = mb_strlen($text);
        }

        return [
            'text' => $text,
            'pt' => $len > 18 ? $smallPt : $normalPt,
        ];
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
            return $roomRaw;
        }

        // LAB02 / A101 style codes stay readable with Ruang prefix when not already present.
        return 'Ruang '.$roomRaw;
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
