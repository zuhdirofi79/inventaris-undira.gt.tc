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
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date');
            $table->date('next_schedule')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled']);
            $table->timestamps();

            $table->index('inventory_item_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
