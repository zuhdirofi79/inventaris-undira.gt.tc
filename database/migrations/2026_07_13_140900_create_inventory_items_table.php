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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_number')->unique();
            $table->string('barcode')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('specification')->nullable();
            $table->unsignedSmallInteger('purchase_year')->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('funding_source')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('depreciation_value', 15, 2)->nullable();
            $table->enum('condition', ['baru', 'bagus', 'cukup', 'rusak_ringan', 'rusak_berat']);
            $table->enum('status', ['aktif', 'rusak_ringan', 'rusak_berat', 'hilang', 'dipinjam', 'maintenance']);
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('responsible_person')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->text('warranty_info')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit')->default('Unit');
            $table->string('group_key')->nullable();
            $table->unsignedInteger('unit_sequence')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('condition');
            $table->index('room_id');
            $table->index('category_id');
            $table->index('group_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
