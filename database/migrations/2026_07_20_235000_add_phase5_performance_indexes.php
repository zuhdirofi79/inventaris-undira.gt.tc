<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5: additive indexes for scan, tree, audit, and soft-delete filters.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->index('barcode');
            $table->index('deleted_at');
            $table->index('created_at');
            $table->index(['room_id', 'category_id'], 'inventory_items_room_category_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex('inventory_items_room_category_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
