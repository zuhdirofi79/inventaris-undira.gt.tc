<?php

namespace App\Observers;

use App\Models\InventoryItem;
use App\Services\InventoryCodeService;

/**
 * Safety net: ensure QR Code exists whenever an InventoryItem is created
 * without going through InventoryCodeService explicitly.
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
    }
}
