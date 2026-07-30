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
        protected InventoryCodeService $codeService,
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
     *     qr_size_mm: float,
     *     logo_width_mm: float,
     *     logo_max_height_mm: float,
     *     font_size_pt: float,
     *     name_max_chars: int,
     *     max_items: int,
     *     cell_width_pct: float,
     *     cell_height_mm: float
     * }
     */
    public function layout(): array
    {
        $columns = max(1, (int) config('inventory_labels.columns', 3));
        $rows = max(1, (int) config('inventory_labels.rows', 10));
        $pageMargin = max(3.0, (float) config('inventory_labels.page_margin_mm', 5));
        $gapX = max(1.0, (float) config('inventory_labels.gap_x_mm', 2.5));
        $gapY = max(1.0, (float) config('inventory_labels.gap_y_mm', 2.0));
        $qrSize = max(20.0, min(24.0, (float) config('inventory_labels.qr_size_mm', 20)));
        $logoWidth = max(8.0, min(15.0, (float) config('inventory_labels.logo_width_mm', 12)));
        $logoMaxHeight = max(3.5, min(8.0, (float) config('inventory_labels.logo_max_height_mm', 5.5)));
        $fontPt = max(7.0, min(10.0, (float) config('inventory_labels.font_size_pt', 8)));

        // A4 usable height ≈ 297mm − 2×margin
        $usableHeight = 297.0 - (2 * $pageMargin);
        $totalGapY = max(0, $rows - 1) * $gapY;
        $cellHeight = ($usableHeight - $totalGapY) / $rows;

        return [
            'columns' => $columns,
            'rows' => $rows,
            'per_page' => $columns * $rows,
            'page_margin_mm' => $pageMargin,
            'gap_x_mm' => $gapX,
            'gap_y_mm' => $gapY,
            'qr_size_mm' => $qrSize,
            'logo_width_mm' => $logoWidth,
            'logo_max_height_mm' => $logoMaxHeight,
            'font_size_pt' => $fontPt,
            'name_max_chars' => max(20, (int) config('inventory_labels.name_max_chars', 48)),
            'max_items' => max(1, (int) config('inventory_labels.max_items', 500)),
            'cell_width_pct' => 100 / $columns,
            'cell_height_mm' => round($cellHeight, 2),
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
     * @param  list<int>  $ids
     * @return Collection<int, object{
     *     inventory_number: string,
     *     name: string,
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
                'room:id,code,name,floor_id',
                'room.floor:id,code,name,floor_number,building_id',
                'room.floor.building:id,code,name',
            ])
            ->orderBy('inventory_number')
            ->get();

        foreach ($items as $item) {
            $number = trim((string) $item->inventory_number);
            if ($number === '') {
                Log::info('Mass label skip: empty inventory number', ['id' => $item->id]);

                continue;
            }

            try {
                $this->codeService->ensureCodes($item);
            } catch (\Throwable $e) {
                Log::warning('Mass label skip: ensureCodes failed', [
                    'id' => $item->id,
                    'inventory_number' => $number,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $qrSrc = $this->qrPngDataUri($number);
            if ($qrSrc === null) {
                Log::warning('Mass label skip: QR PNG failed', [
                    'id' => $item->id,
                    'inventory_number' => $number,
                ]);

                continue;
            }

            $labels->push((object) [
                'inventory_number' => $number,
                'name' => $this->formatName((string) $item->name, $layout['name_max_chars']),
                'location_lines' => $this->locationLines($item),
                'qr_src' => $qrSrc,
            ]);
        }

        return $labels;
    }

    public function downloadPdf(Collection $labels): Response
    {
        $layout = $this->layout();
        $logoSrc = $this->resolveLogoDataUri();

        if ($logoSrc === null) {
            Log::warning('Mass label PDF: UNDIRA logo missing', [
                'expected' => public_path((string) config('inventory_labels.logo_path', 'images/undira.png')),
            ]);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf = Pdf::loadView('exports.inventory-labels-30', [
            'labels' => $labels,
            'layout' => $layout,
            'logoSrc' => $logoSrc,
        ]);
        $pdf->setPaper('a4', 'portrait');

        $perPage = $layout['per_page'];
        $filename = 'inventory_labels_'.$perPage.'_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Embed logo as base64 data-URI so DomPDF works on any host (no HTTP/asset URL).
     */
    public function resolveLogoDataUri(): ?string
    {
        $relative = ltrim(str_replace('\\', '/', (string) config('inventory_labels.logo_path', 'images/undira.png')), '/');
        $candidates = [
            public_path($relative),
            public_path('images/undira.png'),
            base_path('public/'.$relative),
        ];

        foreach (array_unique($candidates) as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $binary = file_get_contents($path);
            if ($binary === false || $binary === '') {
                continue;
            }

            $mime = 'image/png';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $mime = 'image/jpeg';
            } elseif ($ext === 'webp') {
                $mime = 'image/webp';
            }

            return 'data:'.$mime.';base64,'.base64_encode($binary);
        }

        return null;
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
            $quietZone = 2;
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
     * @return list<string>
     */
    protected function locationLines(InventoryItem $item): array
    {
        $room = $item->room;
        $floor = $room?->floor;
        $building = $floor?->building;

        $buildingLine = trim((string) ($building?->name ?: $building?->code ?: ''));
        if ($buildingLine === '') {
            $buildingLine = '—';
        }

        if ($floor?->floor_number !== null) {
            $floorLine = 'Lt.'.$floor->floor_number;
        } else {
            $floorLine = trim((string) ($floor?->name ?: $floor?->code ?: ''));
            if ($floorLine === '') {
                $floorLine = '—';
            }
        }

        $roomRaw = trim((string) ($room?->code ?: $room?->name ?: ''));
        if ($roomRaw === '') {
            $roomLine = '—';
        } elseif (Str::startsWith(Str::lower($roomRaw), 'ruang')) {
            $roomLine = $roomRaw;
        } else {
            $roomLine = 'Ruang '.$roomRaw;
        }

        return [
            Str::limit($buildingLine, 28, '...'),
            Str::limit($floorLine, 16, '...'),
            Str::limit($roomLine, 22, '...'),
        ];
    }
}
