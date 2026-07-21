<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Services\InventoryCodeService;
use Illuminate\Console\Command;

class GenerateInventoryCodesCommand extends Command
{
    protected $signature = 'inventory:generate-codes {--chunk=100 : Batch size}';

    protected $description = 'Generate missing QR codes and barcodes for inventory items';

    public function handle(InventoryCodeService $codeService): int
    {
        $chunk = max(10, (int) $this->option('chunk'));
        $processed = 0;

        InventoryItem::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($items) use ($codeService, &$processed) {
                foreach ($items as $item) {
                    $codeService->ensureCodes($item);
                    $processed++;
                }
                $this->info("Processed {$processed} items...");
            });

        $this->info("Done. Ensured codes for {$processed} inventory items.");

        return self::SUCCESS;
    }
}
