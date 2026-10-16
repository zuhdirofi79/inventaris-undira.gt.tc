<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft-delete + trash meta for riwayat peminjaman (facility + inventaris meta).
     *
     * @var list<string>
     */
    private array $facilityTables = [
        'room_borrowings',
        'vehicle_borrowings',
    ];

    public function up(): void
    {
        foreach ($this->facilityTables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->softDeletes();
                }
                if (! Schema::hasColumn($table, 'deleted_by')) {
                    $blueprint->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($table, 'restored_by')) {
                    $blueprint->foreignId('restored_by')->nullable()->after('deleted_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($table, 'restored_at')) {
                    $blueprint->timestamp('restored_at')->nullable()->after('restored_by');
                }
            });
        }

        if (Schema::hasTable('borrowings')) {
            Schema::table('borrowings', function (Blueprint $blueprint) {
                if (! Schema::hasColumn('borrowings', 'deleted_by')) {
                    $blueprint->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('borrowings', 'restored_by')) {
                    $blueprint->foreignId('restored_by')->nullable()->after('deleted_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('borrowings', 'restored_at')) {
                    $blueprint->timestamp('restored_at')->nullable()->after('restored_by');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->facilityTables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'deleted_by')) {
                    $blueprint->dropConstrainedForeignId('deleted_by');
                }
                if (Schema::hasColumn($table, 'restored_by')) {
                    $blueprint->dropConstrainedForeignId('restored_by');
                }
                if (Schema::hasColumn($table, 'restored_at')) {
                    $blueprint->dropColumn('restored_at');
                }
                if (Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('borrowings')) {
            Schema::table('borrowings', function (Blueprint $blueprint) {
                if (Schema::hasColumn('borrowings', 'deleted_by')) {
                    $blueprint->dropConstrainedForeignId('deleted_by');
                }
                if (Schema::hasColumn('borrowings', 'restored_by')) {
                    $blueprint->dropConstrainedForeignId('restored_by');
                }
                if (Schema::hasColumn('borrowings', 'restored_at')) {
                    $blueprint->dropColumn('restored_at');
                }
            });
        }
    }
};
