<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('operational_vehicles', function (Blueprint $table) {
            $table->unsignedSmallInteger('passenger_capacity')->nullable();
        });

        Schema::table('vehicle_borrowings', function (Blueprint $table) {
            $table->foreignId('route_id')->nullable()->constrained('operational_routes')->nullOnDelete();
            $table->unsignedSmallInteger('passenger_count')->nullable();
            $table->json('passengers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_borrowings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('route_id');
            $table->dropColumn(['passenger_count', 'passengers']);
        });

        Schema::table('operational_vehicles', function (Blueprint $table) {
            $table->dropColumn('passenger_capacity');
        });

        Schema::dropIfExists('operational_routes');
    }
};
