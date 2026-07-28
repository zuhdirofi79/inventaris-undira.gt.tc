<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('borrowings')) {
            return;
        }

        Schema::table('borrowings', function (Blueprint $table) {
            if (! Schema::hasColumn('borrowings', 'approval_number')) {
                $table->string('approval_number', 32)
                    ->nullable()
                    ->unique()
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('borrowings') || ! Schema::hasColumn('borrowings', 'approval_number')) {
            return;
        }

        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropUnique(['approval_number']);
            $table->dropColumn('approval_number');
        });
    }
};
