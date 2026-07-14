<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('to_room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('moved_by')->constrained('users')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('inventory_item_id');
            $table->index('moved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_mutations');
    }
};
