<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operational_drivers')) {
            return;
        }

        Schema::table('operational_drivers', function (Blueprint $table) {
            if (! Schema::hasColumn('operational_drivers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')
                    ->constrained('users')->nullOnDelete();
                $table->unique('user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operational_drivers') || ! Schema::hasColumn('operational_drivers', 'user_id')) {
            return;
        }

        Schema::table('operational_drivers', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
