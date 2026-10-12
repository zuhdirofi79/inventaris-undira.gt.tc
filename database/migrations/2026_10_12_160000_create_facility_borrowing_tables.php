<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $permissions = [
        'room-borrowing.view',
        'room-borrowing.create',
        'room-borrowing.approve',
        'vehicle-borrowing.view',
        'vehicle-borrowing.create',
        'vehicle-borrowing.approve',
        'vehicle-fleet.manage',
    ];

    public function up(): void
    {
        Schema::create('room_borrowings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->string('borrower_name');
            $table->string('borrower_email')->nullable();
            $table->string('borrower_phone', 50);
            $table->text('purpose');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 40)->default('menunggu_persetujuan');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('approval_number')->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['campus_id', 'status']);
        });

        Schema::create('operational_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plate_number')->unique();
            $table->string('category');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('operational_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_borrowings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->string('borrower_name');
            $table->string('borrower_email');
            $table->string('borrower_phone', 50);
            $table->string('unit')->nullable();
            $table->text('purpose');
            $table->string('service_type', 40);
            $table->string('origin');
            $table->string('destination')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 40)->default('menunggu_persetujuan');
            $table->foreignId('assigned_vehicle_id')->nullable()->constrained('operational_vehicles')->restrictOnDelete();
            $table->foreignId('assigned_driver_id')->nullable()->constrained('operational_drivers')->restrictOnDelete();
            $table->string('assigned_service', 40)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('approval_number')->nullable()->unique();
            $table->string('supporting_document_path')->nullable();
            $table->string('supporting_document_original_name')->nullable();
            $table->unsignedInteger('supporting_document_size')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['campus_id', 'status']);
            $table->index(['assigned_vehicle_id', 'status']);
            $table->index(['assigned_driver_id', 'status']);
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_borrowings');
        Schema::dropIfExists('operational_drivers');
        Schema::dropIfExists('operational_vehicles');
        Schema::dropIfExists('room_borrowings');

        Permission::query()->whereIn('name', $this->permissions)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grantPermissions(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $request = [
            'room-borrowing.view',
            'room-borrowing.create',
            'vehicle-borrowing.view',
            'vehicle-borrowing.create',
        ];

        $grants = [
            'viewer' => $request,
            'staff' => $request,
            'admin' => $this->permissions,
            'super-admin' => $this->permissions,
        ];

        foreach ($grants as $roleName => $names) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($names);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
