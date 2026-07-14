<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Room;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InventoryCodeService
{
    public function generateInventoryNumber(Room $room, Category $category, ?string $groupKey = null): string
    {
        $room->loadMissing('floor.building.campus');
        $floor = $room->floor;
        $building = $floor?->building;
        $campus = $building?->campus;

        $campusCode = strtoupper($campus?->code ?? 'XXX');
        $buildingCode = strtoupper($building?->code ?? 'XXX');
        $floorNumber = $floor?->floor_number ?? 0;
        $roomCode = strtoupper($room->code ?? 'XXX');
        $categoryCode = strtoupper($category->code ?? 'XXX');

        $prefix = "UND-{$campusCode}-{$buildingCode}-L{$floorNumber}-{$roomCode}-{$categoryCode}";

        $sequence = $this->nextSequence($prefix, $groupKey);

        return "{$prefix}-{$sequence}";
    }

    public function generateGroupKey(string $name, int $roomId, int $categoryId): string
    {
        return md5(strtolower(trim($name)).":{$roomId}:{$categoryId}");
    }

    public function generateBarcode(string $inventoryNumber): string
    {
        $generator = new BarcodeGeneratorPNG;
        $barcodeData = $generator->getBarcode($inventoryNumber, $generator::TYPE_CODE_128);

        $path = "inventory/barcodes/{$inventoryNumber}.png";
        Storage::disk('public')->put($path, $barcodeData);

        return $path;
    }

    public function generateQrCode(InventoryItem $item): string
    {
        $scanUrl = url('/scan/'.$item->inventory_number);
        $qrData = QrCode::format('png')
            ->size(300)
            ->margin(1)
            ->generate($scanUrl);

        $path = "inventory/qrcodes/{$item->inventory_number}.png";
        Storage::disk('public')->put($path, $qrData);

        return $path;
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

    protected function nextSequence(string $prefix, ?string $groupKey = null): int
    {
        if ($groupKey) {
            $maxInGroup = InventoryItem::query()
                ->where('group_key', $groupKey)
                ->max('unit_sequence');

            return ((int) $maxInGroup) + 1;
        }

        $pattern = $prefix.'-%';
        $latest = InventoryItem::withTrashed()
            ->where('inventory_number', 'like', $pattern)
            ->orderByDesc('id')
            ->value('inventory_number');

        if (! $latest) {
            return 1;
        }

        $parts = explode('-', $latest);

        return ((int) end($parts)) + 1;
    }
}
