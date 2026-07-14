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
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'completed']);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('campus_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
