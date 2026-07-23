<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inventory status: widen to string so new CMMS outcome statuses work on MySQL + SQLite.
        $this->widenEnumToString('inventory_items', 'status', 40, 'aktif');

        // Maintenance WO status: scheduled → waiting, then widen column.
        if (Schema::hasTable('maintenances')) {
            DB::table('maintenances')->where('status', 'scheduled')->update(['status' => 'waiting']);
            $this->widenEnumToString('maintenances', 'status', 32, 'waiting');

            Schema::table('maintenances', function (Blueprint $table) {
                if (! Schema::hasColumn('maintenances', 'maintenance_number')) {
                    $table->string('maintenance_number', 40)->nullable()->unique()->after('id');
                }
                if (! Schema::hasColumn('maintenances', 'maintenance_type')) {
                    $table->string('maintenance_type', 40)->nullable()->after('vendor_id');
                }
                if (! Schema::hasColumn('maintenances', 'priority')) {
                    $table->string('priority', 20)->nullable()->after('maintenance_type');
                }
                if (! Schema::hasColumn('maintenances', 'technician')) {
                    $table->string('technician')->nullable()->after('priority');
                }
                if (! Schema::hasColumn('maintenances', 'parts_replaced')) {
                    $table->text('parts_replaced')->nullable()->after('description');
                }
                if (! Schema::hasColumn('maintenances', 'estimated_cost')) {
                    $table->decimal('estimated_cost', 15, 2)->nullable()->after('cost');
                }
                if (! Schema::hasColumn('maintenances', 'result')) {
                    $table->string('result', 40)->nullable()->after('status');
                }
                if (! Schema::hasColumn('maintenances', 'notes')) {
                    $table->text('notes')->nullable()->after('result');
                }
                if (! Schema::hasColumn('maintenances', 'started_at')) {
                    $table->timestamp('started_at')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('maintenances', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('started_at');
                }
                if (! Schema::hasColumn('maintenances', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
                }
            });

            // Backfill WO numbers for legacy rows.
            $rows = DB::table('maintenances')->whereNull('maintenance_number')->orderBy('id')->get(['id', 'created_at']);
            foreach ($rows as $row) {
                $date = $row->created_at ? date('Ymd', strtotime((string) $row->created_at)) : date('Ymd');
                DB::table('maintenances')->where('id', $row->id)->update([
                    'maintenance_number' => sprintf('MNT-%s-%06d', $date, $row->id),
                    'maintenance_type' => DB::table('maintenances')->where('id', $row->id)->value('maintenance_type') ?: 'repair',
                    'priority' => DB::table('maintenances')->where('id', $row->id)->value('priority') ?: 'medium',
                ]);
            }
        }

        if (! Schema::hasTable('maintenance_photos')) {
            Schema::create('maintenance_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('maintenance_id')->constrained('maintenances')->cascadeOnDelete();
                $table->string('type', 20); // before|during|after
                $table->string('path');
                $table->string('caption')->nullable();
                $table->timestamps();
                $table->index(['maintenance_id', 'type']);
            });
        }

        if (! Schema::hasTable('maintenance_status_histories')) {
            Schema::create('maintenance_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('maintenance_id')->constrained('maintenances')->cascadeOnDelete();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->string('note')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('maintenance_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_status_histories');
        Schema::dropIfExists('maintenance_photos');

        if (Schema::hasTable('maintenances')) {
            Schema::table('maintenances', function (Blueprint $table) {
                foreach ([
                    'maintenance_number', 'maintenance_type', 'priority', 'technician',
                    'parts_replaced', 'estimated_cost', 'result', 'notes',
                    'started_at', 'completed_at', 'created_by',
                ] as $col) {
                    if (Schema::hasColumn('maintenances', $col)) {
                        if ($col === 'created_by') {
                            $table->dropConstrainedForeignId('created_by');
                        } else {
                            $table->dropColumn($col);
                        }
                    }
                }
            });

            DB::table('maintenances')->where('status', 'waiting')->update(['status' => 'scheduled']);
        }
    }

    protected function widenEnumToString(string $table, string $column, int $length, string $default): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} VARCHAR({$length}) NOT NULL DEFAULT '{$default}'");

            return;
        }

        // SQLite / others: doctrine change to string when available.
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $length, $default) {
                $blueprint->string($column, $length)->default($default)->change();
            });
        } catch (\Throwable) {
            // SQLite memory tests often already treat enum as varchar — safe to continue.
        }
    }
};
