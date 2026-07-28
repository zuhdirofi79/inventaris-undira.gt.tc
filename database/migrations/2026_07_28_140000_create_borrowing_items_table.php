<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrowing_id')->constrained('borrowings')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->string('status', 40)->default('menunggu_persetujuan');
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->unique(['borrowing_id', 'inventory_item_id']);
            $table->index(['inventory_item_id', 'status']);
            $table->index('status');
        });

        // Backward compatible: keep header inventory_item_id but allow null for future multi-only rows.
        Schema::table('borrowings', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->change();
        });

        // Backfill existing single-item borrowings into borrowing_items.
        $rows = DB::table('borrowings')
            ->whereNotNull('inventory_item_id')
            ->select(['id', 'inventory_item_id', 'status', 'return_date', 'created_at', 'updated_at'])
            ->get();

        $now = now();
        foreach ($rows->chunk(200) as $chunk) {
            $insert = [];
            foreach ($chunk as $row) {
                $headerStatus = (string) $row->status;
                $itemStatus = match ($headerStatus) {
                    'dikembalikan' => 'dikembalikan',
                    'disetujui', 'dipinjam', 'terlambat' => 'dipinjam',
                    default => 'menunggu_persetujuan', // menunggu_persetujuan + ditolak
                };

                $insert[] = [
                    'borrowing_id' => $row->id,
                    'inventory_item_id' => $row->inventory_item_id,
                    'qty' => 1,
                    'status' => $itemStatus,
                    'returned_at' => $headerStatus === 'dikembalikan'
                        ? ($row->return_date ?? $row->updated_at)
                        : null,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ];
            }

            if ($insert !== []) {
                DB::table('borrowing_items')->insert($insert);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowing_items');

        // Restore NOT NULL only if no nulls exist.
        $nullCount = DB::table('borrowings')->whereNull('inventory_item_id')->count();
        if ($nullCount === 0) {
            Schema::table('borrowings', function (Blueprint $table) {
                $table->foreignId('inventory_item_id')->nullable(false)->change();
            });
        }
    }
};
