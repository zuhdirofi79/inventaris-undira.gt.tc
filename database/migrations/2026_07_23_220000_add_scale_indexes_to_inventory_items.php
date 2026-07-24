<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scale indexes for 100k+ inventory_items (FK filters + purchase year).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $this->addIndexIfMissing($table, 'inventory_items_supplier_id_index', 'supplier_id');
            $this->addIndexIfMissing($table, 'inventory_items_vendor_id_index', 'vendor_id');
            $this->addIndexIfMissing($table, 'inventory_items_org_unit_id_index', 'org_unit_id');
            $this->addIndexIfMissing($table, 'inventory_items_procurement_type_id_index', 'procurement_type_id');
            $this->addIndexIfMissing($table, 'inventory_items_created_by_index', 'created_by');
            $this->addIndexIfMissing($table, 'inventory_items_updated_by_index', 'updated_by');
            $this->addIndexIfMissing($table, 'inventory_items_purchase_year_index', 'purchase_year');
            $this->addIndexIfMissing($table, 'inventory_items_deleted_by_index', 'deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            foreach ([
                'inventory_items_supplier_id_index',
                'inventory_items_vendor_id_index',
                'inventory_items_org_unit_id_index',
                'inventory_items_procurement_type_id_index',
                'inventory_items_created_by_index',
                'inventory_items_updated_by_index',
                'inventory_items_purchase_year_index',
                'inventory_items_deleted_by_index',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                    // Index may not exist on all drivers / prior states.
                }
            }
        });
    }

    protected function addIndexIfMissing(Blueprint $table, string $name, string $column): void
    {
        $sm = Schema::getConnection()->getSchemaBuilder();
        $indexes = collect($sm->getIndexes('inventory_items'))->pluck('name')->all();
        if (! in_array($name, $indexes, true) && Schema::hasColumn('inventory_items', $column)) {
            $table->index($column, $name);
        }
    }
};
