<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Category;
use App\Models\InventoryGlobalSequence;
use App\Models\InventoryItem;
use App\Models\ProcurementType;
use App\Models\Setting;
use App\Models\SubCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Official UNDIRA inventory numbering:
 * {CAMPUS}-{PROCUREMENT}-{YY}-{CATEGORY}-{SUBCATEGORY}-{######}
 *
 * Running number is GLOBAL (never resets). Inventory numbers are immutable after create.
 */
class InventoryCodeService
{
    public const DEFAULT_PADDING = 6;

    public const GLOBAL_SEQUENCE_ID = 1;

    /**
     * Generate official inventory number from master codes + global sequence.
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
        $sequence = $this->allocateGlobalSequence();
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
     */
    public function scanUrl(string $inventoryNumber): string
    {
        $base = rtrim((string) Setting::get('inventory.scan_base_url', ''), '/');
        if ($base !== '') {
            return $base.'/scan/'.rawurlencode($inventoryNumber);
        }

        return route('inventory.scan', ['inventoryNumber' => $inventoryNumber], true);
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
     * Allocate next global sequence (never reused, never resets).
     */
    public function allocateGlobalSequence(): int
    {
        return (int) DB::transaction(function () {
            $row = InventoryGlobalSequence::query()
                ->whereKey(self::GLOBAL_SEQUENCE_ID)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                InventoryGlobalSequence::query()->create([
                    'id' => self::GLOBAL_SEQUENCE_ID,
                    'last_value' => 0,
                ]);
                $row = InventoryGlobalSequence::query()
                    ->whereKey(self::GLOBAL_SEQUENCE_ID)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $row->last_value = ((int) $row->last_value) + 1;
            $row->save();

            return (int) $row->last_value;
        });
    }

    /**
     * Read-only next global sequence preview (does NOT consume).
     *
     * @return array{last_value: int, next_value: int, next_padded: string}
     */
    public function peekNextSequence(?int $campusId = null, ?int $subCategoryId = null, ?int $categoryId = null): array
    {
        $last = (int) (InventoryGlobalSequence::query()->find(self::GLOBAL_SEQUENCE_ID)?->last_value ?? 0);
        $next = $last + 1;
        $padding = $this->padding();

        return [
            'last_value' => $last,
            'next_value' => $next,
            'next_padded' => str_pad((string) $next, $padding, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Reset global counter (renumber command only).
     */
    public function resetGlobalSequence(int $lastValue = 0): void
    {
        DB::transaction(function () use ($lastValue) {
            $row = InventoryGlobalSequence::query()
                ->whereKey(self::GLOBAL_SEQUENCE_ID)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                InventoryGlobalSequence::query()->create([
                    'id' => self::GLOBAL_SEQUENCE_ID,
                    'last_value' => $lastValue,
                ]);

                return;
            }

            $row->last_value = $lastValue;
            $row->save();
        });
    }

    public function padding(): int
    {
        $padding = (int) Setting::get('inventory.sequence_padding', self::DEFAULT_PADDING);

        return $padding < 1 ? self::DEFAULT_PADDING : $padding;
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
