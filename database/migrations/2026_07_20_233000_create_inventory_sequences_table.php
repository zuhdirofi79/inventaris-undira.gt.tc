<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monotonic inventory sequence per campus + classifier (sub_category or category).
     * Soft-deleted assets never reuse sequence values.
     */
    public function up(): void
    {
        Schema::create('inventory_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->string('classifier_type', 32); // sub_category | category
            $table->unsignedBigInteger('classifier_id');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['campus_id', 'classifier_type', 'classifier_id'], 'inventory_sequences_unique_scope');
            $table->index(['classifier_type', 'classifier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sequences');
    }
};
