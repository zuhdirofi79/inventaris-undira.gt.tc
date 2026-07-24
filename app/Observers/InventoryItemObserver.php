<?php

namespace App\Observers;

use App\Models\InventoryItem;
use App\Services\DashboardTreeService;
use App\Services\InventoryCodeService;

/**
 * Safety net: QR codes on create + cache bust on inventory mutations (100k+ scale).
 */
class InventoryItemObserver
{
    public function __construct(
        protected InventoryCodeService $codes,
    ) {}

    public function created(InventoryItem $item): void
    {
        if (blank($item->qr_code_path)) {
            $this->codes->ensureCodes($item);
        }

        $this->bustCaches();
    }

    public function updated(InventoryItem $item): void
    {
        if ($item->wasChanged([
            'status',
            'condition',
            'room_id',
            'category_id',
            'sub_category_id',
            'deleted_at',
        ])) {
            $this->bustCaches();
        }
    }

    public function deleted(InventoryItem $item): void
    {
        $this->bustCaches();
    }

    public function restored(InventoryItem $item): void
    {
        $this->bustCaches();
    }

    public function forceDeleted(InventoryItem $item): void
    {
        $this->bustCaches();
    }

    protected function bustCaches(): void
    {
        DashboardTreeService::flushCache();
    }
}
