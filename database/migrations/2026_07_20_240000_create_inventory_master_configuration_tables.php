<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive masters for inventory configuration page (non-destructive).
     */
    public function up(): void
    {
        Schema::create('room_functions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_master_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->foreignId('floor_id')->constrained()->restrictOnDelete();
            $table->foreignId('org_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_function_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('procurement_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('sub_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_condition_id')->constrained()->restrictOnDelete();
            $table->string('generated_location_code');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('generated_location_code');
            $table->index(['campus_id', 'sub_category_id']);
            $table->index(['campus_id', 'building_id', 'floor_id', 'room_id'], 'imc_location_idx');
            // Uniqueness enforced in Form Request (soft-delete safe).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_master_configurations');
        Schema::dropIfExists('inventory_conditions');
        Schema::dropIfExists('purchase_years');
        Schema::dropIfExists('room_functions');
    }
};
