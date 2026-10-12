<?php

namespace App\Http\Controllers;

use App\Models\OperationalDriver;
use App\Models\OperationalRoute;
use App\Models\OperationalVehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleFleetController extends Controller
{
    public function index(): View
    {
        return view('operations.vehicle-fleet.index', [
            'vehicles' => OperationalVehicle::query()->orderBy('name')->get(),
            'drivers' => OperationalDriver::query()->orderBy('name')->get(),
            'routes' => OperationalRoute::query()->orderBy('name')->get(),
        ]);
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plate_number' => ['required', 'string', 'max:32', Rule::unique('operational_vehicles', 'plate_number')],
            'category' => ['required', 'string', 'max:100'],
            'passenger_capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Nama kendaraan wajib diisi.',
            'plate_number.required' => 'Nomor polisi wajib diisi.',
            'plate_number.unique' => 'Nomor polisi sudah digunakan.',
            'category.required' => 'Jenis kendaraan wajib diisi.',
        ]);

        OperationalVehicle::query()->create($data + ['is_active' => true]);

        return redirect()->route('vehicle-fleet.index')->with('success', 'Kendaraan operasional ditambahkan.');
    }

    public function updateVehicle(Request $request, OperationalVehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plate_number' => ['required', 'string', 'max:32', Rule::unique('operational_vehicles', 'plate_number')->ignore($vehicle->id)],
            'category' => ['required', 'string', 'max:100'],
            'passenger_capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', Rule::in(['0', '1'])],
        ], [
            'name.required' => 'Nama kendaraan wajib diisi.',
            'plate_number.required' => 'Nomor polisi wajib diisi.',
            'plate_number.unique' => 'Nomor polisi sudah digunakan.',
            'category.required' => 'Jenis kendaraan wajib diisi.',
        ]);

        $vehicle->update([
            'name' => $data['name'],
            'plate_number' => $data['plate_number'],
            'category' => $data['category'],
            'passenger_capacity' => $data['passenger_capacity'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] === '1',
        ]);

        return redirect()->route('vehicle-fleet.index')->with('success', 'Data kendaraan diperbarui.');
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ], [
            'name.required' => 'Nama pengemudi wajib diisi.',
        ]);

        OperationalDriver::query()->create($data + ['is_active' => true]);

        return redirect()->route('vehicle-fleet.index')->with('success', 'Pengemudi ditambahkan.');
    }

    public function updateDriver(Request $request, OperationalDriver $driver): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['required', Rule::in(['0', '1'])],
        ], [
            'name.required' => 'Nama pengemudi wajib diisi.',
        ]);

        $driver->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] === '1',
        ]);

        return redirect()->route('vehicle-fleet.index')->with('success', 'Data pengemudi diperbarui.');
    }

    public function storeRoute(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama rute wajib diisi.',
        ]);

        OperationalRoute::query()->create($data + ['is_active' => true]);

        return redirect()->route('vehicle-fleet.index')->with('success', 'Rute ditambahkan.');
    }

    public function updateRoute(Request $request, OperationalRoute $route): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', Rule::in(['0', '1'])],
        ], [
            'name.required' => 'Nama rute wajib diisi.',
        ]);

        $route->update([
            'name' => $data['name'],
            'is_active' => $data['is_active'] === '1',
        ]);

        return redirect()->route('vehicle-fleet.index')->with('success', 'Data rute diperbarui.');
    }
}
