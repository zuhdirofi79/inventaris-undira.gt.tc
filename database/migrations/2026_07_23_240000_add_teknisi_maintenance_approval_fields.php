<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenances', 'damage_type')) {
                $table->string('damage_type')->nullable()->after('description');
            }
            if (! Schema::hasColumn('maintenances', 'analysis')) {
                $table->text('analysis')->nullable()->after('damage_type');
            }
            if (! Schema::hasColumn('maintenances', 'work_performed')) {
                $table->text('work_performed')->nullable()->after('analysis');
            }
            if (! Schema::hasColumn('maintenances', 'estimated_completion')) {
                $table->date('estimated_completion')->nullable()->after('next_schedule');
            }
            if (! Schema::hasColumn('maintenances', 'technician_user_id')) {
                $table->foreignId('technician_user_id')->nullable()->after('technician')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('maintenances', 'submitted_for_approval_at')) {
                $table->timestamp('submitted_for_approval_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('maintenances', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('submitted_for_approval_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('maintenances', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('maintenances', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('maintenances', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_note');
            }
            if (! Schema::hasColumn('maintenances', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            foreach ([
                'damage_type', 'analysis', 'work_performed', 'estimated_completion',
                'submitted_for_approval_at', 'approved_at', 'approval_note', 'rejection_reason',
            ] as $col) {
                if (Schema::hasColumn('maintenances', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('maintenances', 'technician_user_id')) {
                $table->dropConstrainedForeignId('technician_user_id');
            }
            if (Schema::hasColumn('maintenances', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            if (Schema::hasColumn('maintenances', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
