<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2: Master Data SSOT — Unit/Division + Procurement Type.
     */
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('procurement_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('org_unit_id')->nullable()->after('room_id')->constrained('org_units')->nullOnDelete();
            $table->foreignId('procurement_type_id')->nullable()->after('funding_source')->constrained('procurement_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('org_unit_id');
            $table->dropConstrainedForeignId('procurement_type_id');
        });

        Schema::dropIfExists('procurement_types');
        Schema::dropIfExists('org_units');
    }
};
