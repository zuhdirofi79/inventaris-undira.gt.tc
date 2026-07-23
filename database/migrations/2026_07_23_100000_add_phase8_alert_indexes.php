<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->index('warranty_expiry', 'inventory_items_warranty_expiry_index');
        });

        Schema::table('maintenances', function (Blueprint $table) {
            $table->index('next_schedule', 'maintenances_next_schedule_index');
        });

        Schema::table('borrowings', function (Blueprint $table) {
            $table->index(['status', 'expected_return_date'], 'borrowings_status_expected_return_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex('inventory_items_warranty_expiry_index');
        });

        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropIndex('maintenances_next_schedule_index');
        });

        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropIndex('borrowings_status_expected_return_index');
        });
    }
};
