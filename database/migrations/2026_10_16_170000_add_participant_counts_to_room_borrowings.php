<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_borrowings', function (Blueprint $table) {
            $table->unsignedInteger('participant_internal_count')->default(0)->after('participant_internal');
            $table->unsignedInteger('participant_external_count')->default(0)->after('participant_external');
        });
    }

    public function down(): void
    {
        Schema::table('room_borrowings', function (Blueprint $table) {
            $table->dropColumn([
                'participant_internal_count',
                'participant_external_count',
            ]);
        });
    }
};
