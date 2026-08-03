<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventorySequence;
use App\Models\ProcurementType;
use App\Models\SubCategory;
use App\Support\AppSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Official UNDIRA inventory numbering:
 * {CAMPUS}-{PROCUREMENT}-{YY}-{CATEGORY}-{SUBCATEGORY}-{######}
 *
 * Running number is scoped by campus_id + sub_category_id (never global, never recycled).
 */
class InventoryCodeService
{
    public const DEFAULT_PADDING = 6;

    /**
     * Generate official inventory number from master codes + scoped sequence.
     */
    public function generateInventoryNumber(
        Campus $campus,
        ProcurementType $procurement,
        int $purchaseYear,
        Category $category,
        SubCategory $subCategory,
    ): string {
        $campusCode = $this->requireCode($campus->code, 'Kode kampus');
        $procurementCode = $this->requireCode($procurement->code, 'Kode jenis pengadaan');
        $categoryCode = $this->requireCode($category->code, 'Kode kategori');
        $subCode = $this->requireCode($subCategory->code, 'Kode subkategori');

        if ($purchaseYear < 1900 || $purchaseYear > 2100) {
            throw new \RuntimeException('Tahun pengadaan tidak valid.');
        }

        $year2 = str_pad((string) ($purchaseYear % 100), 2, '0', STR_PAD_LEFT);
        $sequence = $this->allocateSequence((int) $campus->id, (int) $subCategory->id);
        $padding = $this->padding();

        return implode('-', [
            $campusCode,
            $procurementCode,
            $year2,
            $categoryCode,
            $subCode,
            str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Build number from known sequence value (used by renumber / dry-run preview).
     */
    public function formatInventoryNumber(
        Campus $campus,
        ProcurementType $procurement,
        int $purchaseYear,
        Category $category,
        SubCategory $subCategory,
        int $sequence,
    ): string {
        $year2 = str_pad((string) ($purchaseYear % 100), 2, '0', STR_PAD_LEFT);
        $padding = $this->padding();

        return implode('-', [
            $this->requireCode($campus->code, 'Kode kampus'),
            $this->requireCode($procurement->code, 'Kode jenis pengadaan'),
            $year2,
            $this->requireCode($category->code, 'Kode kategori'),
            $this->requireCode($subCategory->code, 'Kode subkategori'),
            str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    public function generateGroupKey(string $name, int $roomId, int $categoryId): string
    {
        return md5(strtolower(trim($name)).":{$roomId}:{$categoryId}");
    }

    /**
     * QR encodes plain inventory number only (not URL / JSON).
     */
    public function generateQrCode(InventoryItem $item): string
    {
        $payload = (string) $item->inventory_number;
        if ($payload === '') {
            throw new \RuntimeException('Nomor inventaris kosong; QR tidak dapat digenerate.');
        }

        $qrData = QrCode::format('svg')
            ->size(300)
            ->margin(1)
            ->generate($payload);

        $safeName = str_replace(['/', '\\'], '-', $payload);
        $path = "inventory/qrcodes/{$safeName}.svg";
        Storage::disk('public')->put($path, $qrData);

        return $path;
    }

    /**
     * Public scan page URL (for links in UI — not embedded in QR).
     * Empty inventory.scan_base_url → config('app.url').
     */
    public function scanUrl(string $inventoryNumber): string
    {
        $base = AppSettings::scanBaseUrl();

        return $base.'/scan/'.rawurlencode($inventoryNumber);
    }

    public function generateAndSaveCodes(InventoryItem $item): InventoryItem
    {
        $item->qr_code_path = $this->generateQrCode($item);
        $item->save();

        return $item->fresh();
    }

    /**
     * Ensure QR file exists for the current immutable inventory number.
     */
    public function ensureCodes(InventoryItem $item): InventoryItem
    {
        $disk = Storage::disk('public');
        $needsSave = false;

        $safeName = str_replace(['/', '\\'], '-', (string) $item->inventory_number);
        $qrPath = $item->qr_code_path ?: "inventory/qrcodes/{$safeName}.svg";

        if (! $item->qr_code_path || ! $disk->exists($qrPath)) {
            $item->qr_code_path = $this->generateQrCode($item);
            $needsSave = true;
        }

        if ($needsSave) {
            $item->save();
        }

        return $item->fresh() ?? $item;
    }

    /**
     * Allocate next sequence for campus + sub_category (never reused, never resets).
     * Uses lockForUpdate inside a transaction to prevent race duplicates.
     */
    public function allocateSequence(int $campusId, int $subCategoryId): int
    {
        if ($campusId < 1 || $subCategoryId < 1) {
            throw new \InvalidArgumentException('campus_id dan sub_category_id wajib untuk alokasi sequence.');
        }

        return (int) DB::transaction(function () use ($campusId, $subCategoryId) {
            $row = InventorySequence::query()
                ->where('campus_id', $campusId)
                ->where('sub_category_id', $subCategoryId)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                InventorySequence::query()->create([
                    'campus_id' => $campusId,
                    'sub_category_id' => $subCategoryId,
                    'last_value' => 0,
                ]);

                $row = InventorySequence::query()
                    ->where('campus_id', $campusId)
                    ->where('sub_category_id', $subCategoryId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $row->last_value = ((int) $row->last_value) + 1;
            $row->save();

            return (int) $row->last_value;
        });
    }

    /**
     * Read-only next sequence preview for campus + sub_category (does NOT consume).
     *
     * @return array{last_value: int, next_value: int, next_padded: string, campus_id: int|null, sub_category_id: int|null}
     */
    public function peekNextSequence(?int $campusId = null, ?int $subCategoryId = null, ?int $categoryId = null): array
    {
        $padding = $this->padding();

        if (! $campusId || ! $subCategoryId) {
            return [
                'last_value' => 0,
                'next_value' => 1,
                'next_padded' => str_pad('1', $padding, '0', STR_PAD_LEFT),
                'campus_id' => $campusId,
                'sub_category_id' => $subCategoryId,
            ];
        }

        $last = (int) (InventorySequence::query()
            ->where('campus_id', $campusId)
            ->where('sub_category_id', $subCategoryId)
            ->value('last_value') ?? 0);

        $next = $last + 1;

        return [
            'last_value' => $last,
            'next_value' => $next,
            'next_padded' => str_pad((string) $next, $padding, '0', STR_PAD_LEFT),
            'campus_id' => $campusId,
            'sub_category_id' => $subCategoryId,
        ];
    }

    /**
     * Reset all scoped counters (renumber / purge only).
     */
    public function resetAllSequences(): void
    {
        InventorySequence::query()->update(['last_value' => 0]);
    }

    public function padding(): int
    {
        return AppSettings::sequencePadding();
    }

    protected function requireCode(?string $code, string $label): string
    {
        $normalized = strtoupper(trim((string) $code));
        if ($normalized === '') {
            throw new \RuntimeException("{$label} wajib diisi di master data.");
        }

        return $normalized;
    }
}
