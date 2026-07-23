<?php

namespace Tests\Feature;

use App\Enums\MaintenanceStatus;
use App\Http\Requests\ScanStockOpnameRequest;
use App\Http\Requests\StoreStockOpnameRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FormRequestPhase1Test extends TestCase
{
    public function test_store_stock_opname_request_messages_are_indonesian(): void
    {
        $request = StoreStockOpnameRequest::create('/stock-opnames', 'POST', []);
        $validator = Validator::make([], $request->rules(), $request->messages(), $request->attributes());

        $this->assertTrue($validator->fails());
        $this->assertSame('Judul stock opname wajib diisi.', $validator->errors()->first('title'));
        $this->assertSame('Kampus wajib dipilih.', $validator->errors()->first('campus_id'));
    }

    public function test_scan_stock_opname_request_messages_are_indonesian(): void
    {
        $request = ScanStockOpnameRequest::create('/scan', 'POST', []);
        $validator = Validator::make([], $request->rules(), $request->messages(), $request->attributes());

        $this->assertTrue($validator->fails());
        $this->assertSame('Nomor inventaris wajib diisi.', $validator->errors()->first('inventory_number'));
    }

    public function test_store_user_request_messages_are_indonesian(): void
    {
        $request = StoreUserRequest::create('/admin/users', 'POST', [
            'email' => 'bukan-email',
        ]);
        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('Nama pengguna wajib diisi.', $validator->errors()->first('name'));
        $this->assertSame('Email tidak valid.', $validator->errors()->first('email'));
        $this->assertSame('Peran wajib dipilih.', $validator->errors()->first('roles'));
    }

    public function test_update_user_request_normalizes_absent_is_active_to_false(): void
    {
        $user = new User(['id' => 1]);
        $request = UpdateUserRequest::create('/admin/users/1', 'PUT', [
            'name' => 'Admin',
            'email' => 'admin@undira.ac.id',
        ]);
        $request->setRouteResolver(function () use ($request, $user) {
            $route = new Route('PUT', '/admin/users/{user}', []);
            $route->bind($request);
            $route->setParameter('user', $user);

            return $route;
        });

        $method = new \ReflectionMethod(UpdateUserRequest::class, 'prepareForValidation');
        $method->invoke($request);

        $this->assertFalse($request->boolean('is_active'));
    }

    public function test_update_maintenance_request_rejects_invalid_status(): void
    {
        $maintenance = new Maintenance(['id' => 1]);
        $request = UpdateMaintenanceRequest::create('/maintenances/1', 'PUT', [
            'status' => 'bukan-status',
            'service_date' => '2026-01-01',
            'next_schedule' => '2025-01-01',
        ]);
        $request->setRouteResolver(function () use ($request, $maintenance) {
            $route = new Route('PUT', '/maintenances/{maintenance}', []);
            $route->bind($request);
            $route->setParameter('maintenance', $maintenance);

            return $route;
        });

        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('Status maintenance tidak valid.', $validator->errors()->first('status'));
        $this->assertSame(
            'Jadwal berikutnya tidak boleh sebelum tanggal servis.',
            $validator->errors()->first('next_schedule'),
        );
        $this->assertContains(MaintenanceStatus::Completed->value, array_column(MaintenanceStatus::cases(), 'value'));
    }
}
