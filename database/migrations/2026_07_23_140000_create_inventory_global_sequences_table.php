<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global monotonic inventory running number (never resets).
     */
    public function up(): void
    {
        Schema::create('inventory_global_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });

        DB::table('inventory_global_sequences')->insert([
            'id' => 1,
            'last_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_global_sequences');
    }
};
