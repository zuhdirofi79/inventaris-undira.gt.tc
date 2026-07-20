<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventorySequence;
use App\Models\OrgUnit;
use App\Models\Room;
use App\Models\Setting;
use App\Models\SubCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InventoryCodeService
{
    public const DEFAULT_FORMAT = '{campus}-{org_unit}-{sub_category}-{sequence}';

    public const DEFAULT_PADDING = 6;

    /**
     * Generate inventory number using configurable master-code format.
     * Sequence is allocated per campus + sub_category (fallback: category), never reused.
     */
    public function generateInventoryNumber(
        Room $room,
        Category $category,
        ?SubCategory $subCategory = null,
        ?OrgUnit $orgUnit = null,
    ): string {
        $room->loadMissing('floor.building.campus');

        $campus = $room->floor?->building?->campus;
        $campusId = $campus?->id;
        if (! $campusId) {
            throw new \RuntimeException('Ruangan tidak terhubung ke kampus.');
        }

        $sequence = $this->allocateSequence(
            $campusId,
            $subCategory?->id,
            $category->id,
        );

        $tokens = $this->formatTokens($room, $category, $subCategory, $orgUnit, $sequence);
        $pattern = (string) Setting::get('inventory.number_format', self::DEFAULT_FORMAT);

        return $this->applyFormat($pattern, $tokens);
    }

    public function generateGroupKey(string $name, int $roomId, int $categoryId): string
    {
        return md5(strtolower(trim($name)).":{$roomId}:{$categoryId}");
    }

    public function generateBarcode(string $inventoryNumber): string
    {
        $generator = new BarcodeGeneratorPNG;
        $barcodeData = $generator->getBarcode($inventoryNumber, $generator::TYPE_CODE_128);

        $safeName = str_replace(['/', '\\'], '-', $inventoryNumber);
        $path = "inventory/barcodes/{$safeName}.png";
        Storage::disk('public')->put($path, $barcodeData);

        return $path;
    }

    public function generateQrCode(InventoryItem $item): string
    {
        $scanUrl = $this->scanUrl($item->inventory_number);

        // SVG avoids Imagick dependency (often missing on low-end/dev environments).
        $qrData = QrCode::format('svg')
            ->size(300)
            ->margin(1)
            ->generate($scanUrl);

        $safeName = str_replace(['/', '\\'], '-', $item->inventory_number);
        $path = "inventory/qrcodes/{$safeName}.svg";
        Storage::disk('public')->put($path, $qrData);

        return $path;
    }

    public function scanUrl(string $inventoryNumber): string
    {
        $base = rtrim((string) Setting::get('inventory.scan_base_url', ''), '/');
        if ($base === '') {
            return url('/scan/'.$inventoryNumber);
        }

        return $base.'/scan/'.$inventoryNumber;
    }

    public function generateAndSaveCodes(InventoryItem $item): InventoryItem
    {
        if (! $item->barcode) {
            $item->barcode = $item->inventory_number;
        }

        $item->qr_code_path = $this->generateQrCode($item);
        $this->generateBarcode($item->inventory_number);
        $item->save();

        return $item->fresh();
    }

    /**
     * Allocate next monotonic sequence for campus + classifier.
     * Soft-deleted items never free a sequence value.
     */
    public function allocateSequence(int $campusId, ?int $subCategoryId, int $categoryId): int
    {
        $classifierType = $subCategoryId ? 'sub_category' : 'category';
        $classifierId = $subCategoryId ?? $categoryId;

        return (int) DB::transaction(function () use ($campusId, $classifierType, $classifierId) {
            $row = InventorySequence::query()
                ->where('campus_id', $campusId)
                ->where('classifier_type', $classifierType)
                ->where('classifier_id', $classifierId)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $bootstrapped = $this->bootstrapSequenceValue($campusId, $classifierType, $classifierId);

                $row = InventorySequence::query()->create([
                    'campus_id' => $campusId,
                    'classifier_type' => $classifierType,
                    'classifier_id' => $classifierId,
                    'last_value' => $bootstrapped,
                ]);

                $row = InventorySequence::query()
                    ->whereKey($row->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $row->last_value = ((int) $row->last_value) + 1;
            $row->save();

            return (int) $row->last_value;
        });
    }

    protected function bootstrapSequenceValue(int $campusId, string $classifierType, int $classifierId): int
    {
        $query = InventoryItem::withTrashed()
            ->whereHas('room.floor.building', fn ($q) => $q->where('campus_id', $campusId));

        if ($classifierType === 'sub_category') {
            $query->where('sub_category_id', $classifierId);
        } else {
            $query->where('category_id', $classifierId)->whereNull('sub_category_id');
        }

        $max = 0;
        foreach ($query->pluck('inventory_number') as $number) {
            $parts = explode('-', (string) $number);
            $tail = (int) end($parts);
            if ($tail > $max) {
                $max = $tail;
            }
        }

        return $max;
    }

    /**
     * @return array<string, string>
     */
    protected function formatTokens(
        Room $room,
        Category $category,
        ?SubCategory $subCategory,
        ?OrgUnit $orgUnit,
        int $sequence,
    ): array {
        $floor = $room->floor;
        $building = $floor?->building;
        $campus = $building?->campus;
        $padding = (int) Setting::get('inventory.sequence_padding', self::DEFAULT_PADDING);
        if ($padding < 1) {
            $padding = self::DEFAULT_PADDING;
        }

        $subCode = strtoupper($subCategory?->code ?: ($category->code ?? 'XXX'));

        return [
            'campus' => strtoupper($campus?->code ?? 'XXX'),
            'building' => strtoupper($building?->code ?? 'XXX'),
            'floor' => 'L'.($floor?->floor_number ?? 0),
            'floor_code' => strtoupper($floor?->code ?? 'XXX'),
            'room' => strtoupper($room->code ?? 'XXX'),
            'category' => strtoupper($category->code ?? 'XXX'),
            'sub_category' => $subCode,
            'org_unit' => strtoupper($orgUnit?->code ?? 'NA'),
            'sequence' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * @param  array<string, string>  $tokens
     */
    protected function applyFormat(string $pattern, array $tokens): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z_]+)\}/i',
            function (array $matches) use ($tokens) {
                $key = strtolower($matches[1]);

                return $tokens[$key] ?? $matches[0];
            },
            $pattern
        );
    }
}
