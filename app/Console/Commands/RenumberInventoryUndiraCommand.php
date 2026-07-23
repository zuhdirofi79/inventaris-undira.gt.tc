<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Services\InventoryCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One-time regenerate of all inventory numbers to UNDIRA official format + QR payloads.
 *
 * Format: {CAMPUS}-{PROCUREMENT}-{YY}-{CATEGORY}-{SUB}-{######}
 * Sequence is GLOBAL.
 */
class RenumberInventoryUndiraCommand extends Command
{
    protected $signature = 'inventory:renumber-undira
        {--dry-run : Preview without writing}
        {--force : Apply changes}';

    protected $description = 'Regenerate all inventory numbers to UNDIRA format and rebuild QR codes (plain number payload)';

    public function handle(InventoryCodeService $codes): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->error('Specify --dry-run to preview or --force to apply.');

            return self::FAILURE;
        }

        $items = InventoryItem::withTrashed()
            ->with([
                'room.floor.building.campus',
                'category',
                'subCategory',
                'procurementType',
            ])
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            $this->info('No inventory items found.');

            return self::SUCCESS;
        }

        $plan = [];
        $failures = [];

        foreach ($items as $item) {
            $campus = $item->room?->floor?->building?->campus;
            $category = $item->category;
            $sub = $item->subCategory;
            $procurement = $item->procurementType;
            $year = (int) ($item->purchase_year
                ?: ($item->purchase_date?->year ?? $item->created_at?->year ?? 0));

            if (! $campus || ! $category || ! $sub || ! $procurement || $year < 1900) {
                $failures[] = [
                    'id' => $item->id,
                    'old' => $item->inventory_number,
                    'reason' => 'Missing campus/category/subcategory/procurement/purchase_year',
                ];

                continue;
            }

            try {
                // Sequence preview only — real allocate happens in force pass.
                $plan[] = [
                    'item' => $item,
                    'campus' => $campus,
                    'procurement' => $procurement,
                    'year' => $year,
                    'category' => $category,
                    'sub' => $sub,
                ];
            } catch (\Throwable $e) {
                $failures[] = [
                    'id' => $item->id,
                    'old' => $item->inventory_number,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        if ($failures !== []) {
            $this->warn('Cannot renumber '.count($failures).' item(s). Fix master data first:');
            foreach (array_slice($failures, 0, 20) as $fail) {
                $this->line("  #{$fail['id']} {$fail['old']}: {$fail['reason']}");
            }
            if (count($failures) > 20) {
                $this->line('  … and '.(count($failures) - 20).' more');
            }

            if ($force) {
                $this->error('Aborting --force because some items are incomplete.');

                return self::FAILURE;
            }
        }

        $this->info('Eligible items: '.count($plan).' / '.$items->count());

        if ($dryRun) {
            $seq = 0;
            foreach ($plan as $row) {
                $seq++;
                $preview = $codes->formatInventoryNumber(
                    $row['campus'],
                    $row['procurement'],
                    $row['year'],
                    $row['category'],
                    $row['sub'],
                    $seq,
                );
                $this->line("  {$row['item']->inventory_number} → {$preview}");
            }
            $this->info('Dry run complete. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $codes) {
            $codes->resetGlobalSequence(0);

            // Phase 1: temporary unique numbers to avoid unique collisions.
            foreach ($plan as $row) {
                /** @var InventoryItem $item */
                $item = $row['item'];
                $item->inventory_number = 'TMP-'.$item->id;
                $item->saveQuietly();
            }

            // Phase 2: assign official numbers + QR.
            foreach ($plan as $row) {
                /** @var InventoryItem $item */
                $item = $row['item']->fresh();
                $oldQr = $item->qr_code_path;

                $number = $codes->generateInventoryNumber(
                    $row['campus'],
                    $row['procurement'],
                    $row['year'],
                    $row['category'],
                    $row['sub'],
                );

                $item->inventory_number = $number;
                $item->qr_code_path = null;
                $item->saveQuietly();

                $codes->generateAndSaveCodes($item->fresh());

                if ($oldQr && $oldQr !== $item->fresh()->qr_code_path && Storage::disk('public')->exists($oldQr)) {
                    Storage::disk('public')->delete($oldQr);
                }

                $this->line("  #{$item->id} → {$number}");
            }
        });

        $this->info('Renumber complete. Reprint QR labels as needed.');

        return self::SUCCESS;
    }
}
