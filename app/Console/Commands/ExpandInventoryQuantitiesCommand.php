<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\OrgUnit;
use App\Models\SubCategory;
use App\Services\InventoryCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Expand legacy aggregate rows (quantity > 1) into 1-asset-1-record units.
 */
class ExpandInventoryQuantitiesCommand extends Command
{
    protected $signature = 'inventory:expand-quantities {--dry-run : Preview without writing}';

    protected $description = 'Expand inventory rows with quantity > 1 into individual unit records';

    public function handle(InventoryCodeService $codeService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $aggregates = InventoryItem::query()
            ->where('quantity', '>', 1)
            ->with(['room.floor.building.campus', 'category', 'subCategory', 'orgUnit'])
            ->get();

        if ($aggregates->isEmpty()) {
            $this->info('No aggregate inventory rows found.');

            return self::SUCCESS;
        }

        $this->info('Found '.$aggregates->count().' aggregate row(s).');

        foreach ($aggregates as $aggregate) {
            $extra = ((int) $aggregate->quantity) - 1;
            $this->line("- {$aggregate->inventory_number}: quantity={$aggregate->quantity} → keep 1 + create {$extra}");

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($aggregate, $extra, $codeService) {
                $room = $aggregate->room;
                $category = $aggregate->category;
                $subCategory = $aggregate->subCategory;
                $orgUnit = $aggregate->orgUnit;
                $groupKey = $aggregate->group_key ?: $codeService->generateGroupKey(
                    $aggregate->name,
                    $aggregate->room_id,
                    $aggregate->category_id,
                );

                $aggregate->update([
                    'quantity' => 1,
                    'group_key' => $groupKey,
                    'unit_sequence' => $aggregate->unit_sequence ?: 1,
                ]);

                if (! $aggregate->barcode || ! $aggregate->qr_code_path) {
                    $codeService->generateAndSaveCodes($aggregate);
                }

                for ($i = 0; $i < $extra; $i++) {
                    $number = $codeService->generateInventoryNumber(
                        $room,
                        $category,
                        $subCategory instanceof SubCategory ? $subCategory : null,
                        $orgUnit instanceof OrgUnit ? $orgUnit : null,
                    );

                    $clone = $aggregate->replicate([
                        'inventory_number',
                        'barcode',
                        'qr_code_path',
                        'unit_sequence',
                    ]);
                    $clone->inventory_number = $number;
                    $clone->barcode = null;
                    $clone->qr_code_path = null;
                    $clone->quantity = 1;
                    $clone->group_key = $groupKey;
                    $clone->unit_sequence = ((int) ($aggregate->unit_sequence ?: 1)) + $i + 1;
                    $clone->save();

                    $codeService->generateAndSaveCodes($clone);
                }
            });
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Expansion complete.');

        return self::SUCCESS;
    }
}
