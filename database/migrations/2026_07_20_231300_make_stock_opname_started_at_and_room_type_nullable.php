<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow draft stock opnames without started_at, and rooms without room_type.
     */
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->change();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable(false)->change();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_type')->nullable(false)->change();
        });
    }
};
