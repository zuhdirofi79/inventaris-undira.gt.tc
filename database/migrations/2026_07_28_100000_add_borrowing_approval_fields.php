<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand borrowing status + approval metadata without dropping existing rows.
     */
    public function up(): void
    {
        if (! Schema::hasTable('borrowings')) {
            return;
        }

        $this->widenStatusColumn();

        Schema::table('borrowings', function (Blueprint $table) {
            if (! Schema::hasColumn('borrowings', 'requested_by')) {
                $table->foreignId('requested_by')
                    ->nullable()
                    ->after('approved_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('borrowings', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('requested_by');
            }

            if (! Schema::hasColumn('borrowings', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('borrowings', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }

            if (! Schema::hasColumn('borrowings', 'approval_notes')) {
                $table->text('approval_notes')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('borrowings', 'notification_sent')) {
                $table->boolean('notification_sent')->default(false)->after('approval_notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('borrowings')) {
            return;
        }

        Schema::table('borrowings', function (Blueprint $table) {
            if (Schema::hasColumn('borrowings', 'requested_by')) {
                $table->dropConstrainedForeignId('requested_by');
            }

            $drop = array_values(array_filter([
                Schema::hasColumn('borrowings', 'approved_at') ? 'approved_at' : null,
                Schema::hasColumn('borrowings', 'rejected_at') ? 'rejected_at' : null,
                Schema::hasColumn('borrowings', 'rejection_reason') ? 'rejection_reason' : null,
                Schema::hasColumn('borrowings', 'approval_notes') ? 'approval_notes' : null,
                Schema::hasColumn('borrowings', 'notification_sent') ? 'notification_sent' : null,
            ]));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    protected function widenStatusColumn(): void
    {
        if (! Schema::hasColumn('borrowings', 'status')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE borrowings MODIFY status VARCHAR(40) NOT NULL DEFAULT 'dipinjam'");

            return;
        }

        try {
            Schema::table('borrowings', function (Blueprint $table) {
                $table->string('status', 40)->default('dipinjam')->change();
            });
        } catch (\Throwable) {
            // SQLite tests often already store enums as varchar.
        }
    }
};
