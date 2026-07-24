<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // inventory_number already unique; status/room_id/category_id already indexed.
            if (! $this->indexExists('inventory_items', 'inventory_items_sub_category_id_index')) {
                $table->index('sub_category_id', 'inventory_items_sub_category_id_index');
            }
            if (! $this->indexExists('inventory_items', 'inventory_items_room_status_index')) {
                $table->index(['room_id', 'status'], 'inventory_items_room_status_index');
            }
            if (! $this->indexExists('inventory_items', 'inventory_items_room_cat_sub_status_index')) {
                $table->index(
                    ['room_id', 'category_id', 'sub_category_id', 'status'],
                    'inventory_items_room_cat_sub_status_index'
                );
            }
        });

        Schema::table('buildings', function (Blueprint $table) {
            if (! $this->indexExists('buildings', 'buildings_campus_id_index')) {
                $table->index('campus_id', 'buildings_campus_id_index');
            }
        });

        Schema::table('floors', function (Blueprint $table) {
            if (! $this->indexExists('floors', 'floors_building_id_index')) {
                $table->index('building_id', 'floors_building_id_index');
            }
        });

        Schema::table('rooms', function (Blueprint $table) {
            if (! $this->indexExists('rooms', 'rooms_floor_id_index')) {
                $table->index('floor_id', 'rooms_floor_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            foreach ([
                'inventory_items_sub_category_id_index',
                'inventory_items_room_status_index',
                'inventory_items_room_cat_sub_status_index',
            ] as $name) {
                if ($this->indexExists('inventory_items', $name)) {
                    $table->dropIndex($name);
                }
            }
        });

        Schema::table('buildings', function (Blueprint $table) {
            if ($this->indexExists('buildings', 'buildings_campus_id_index')) {
                $table->dropIndex('buildings_campus_id_index');
            }
        });

        Schema::table('floors', function (Blueprint $table) {
            if ($this->indexExists('floors', 'floors_building_id_index')) {
                $table->dropIndex('floors_building_id_index');
            }
        });

        Schema::table('rooms', function (Blueprint $table) {
            if ($this->indexExists('rooms', 'rooms_floor_id_index')) {
                $table->dropIndex('rooms_floor_id_index');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $sm = Schema::getConnection()->getSchemaBuilder();
        if (! method_exists($sm, 'getIndexes')) {
            return false;
        }

        foreach ($sm->getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
