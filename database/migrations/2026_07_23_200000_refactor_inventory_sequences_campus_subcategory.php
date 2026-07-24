<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sequence scope: one counter per campus_id + sub_category_id.
     * Values are never recycled (soft-delete / destroy keep last_value).
     */
    public function up(): void
    {
        Schema::dropIfExists('inventory_sequences');

        Schema::create('inventory_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_category_id')->constrained('sub_categories')->cascadeOnDelete();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['campus_id', 'sub_category_id'], 'inventory_sequences_campus_sub_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sequences');

        Schema::create('inventory_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->string('classifier_type', 32);
            $table->unsignedBigInteger('classifier_id');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
            $table->unique(['campus_id', 'classifier_type', 'classifier_id'], 'inventory_sequences_unique_scope');
            $table->index(['classifier_type', 'classifier_id']);
        });
    }
};
