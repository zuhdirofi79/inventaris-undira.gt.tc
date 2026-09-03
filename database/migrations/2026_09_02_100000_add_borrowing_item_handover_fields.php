<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowing_items', function (Blueprint $table) {
            $table->timestamp('borrowed_at')->nullable()->after('status');
            $table->text('rejection_reason')->nullable()->after('borrowed_at');
        });
    }

    public function down(): void
    {
        Schema::table('borrowing_items', function (Blueprint $table) {
            $table->dropColumn(['borrowed_at', 'rejection_reason']);
        });
    }
};
