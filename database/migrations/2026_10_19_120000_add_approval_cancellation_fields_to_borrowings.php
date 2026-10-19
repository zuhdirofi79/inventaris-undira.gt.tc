<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['borrowings', 'room_borrowings', 'vehicle_borrowings'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('rejection_reason');
                }
                if (! Schema::hasColumn($tableName, 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                        ->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable()->after('cancelled_by');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['borrowings', 'room_borrowings', 'vehicle_borrowings'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'cancelled_by')) {
                    $table->dropConstrainedForeignId('cancelled_by');
                }
                $drop = array_values(array_filter([
                    Schema::hasColumn($tableName, 'cancelled_at') ? 'cancelled_at' : null,
                    Schema::hasColumn($tableName, 'cancellation_reason') ? 'cancellation_reason' : null,
                ]));
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};
