<?php

namespace App\Http\Controllers;

use App\Models\OperationalDriver;
use App\Models\OperationalRoute;
use App\Models\OperationalVehicle;
use App\Models\User;
use App\Services\OperationalDriverDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VehicleFleetController extends Controller
{
    public function __construct(
        private readonly OperationalDriverDirectory $drivers,
    ) {}

    public function index(): View
    {
        $roster = $this->drivers->masterList();
        $linkableUsers = User::query()
            ->role(OperationalDriverDirectory::ROLE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        return view('operations.vehicle-fleet.index', [
            'vehicles' => OperationalVehicle::query()->orderBy('name')->get(),
            'drivers' => $roster,
            'routes' => OperationalRoute::query()->orderBy('name')->get(),
            'linkableDriverUsers' => $linkableUsers,
            'usersManageUrl' => route('admin.users.index'),
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

    /**
     * Link / refresh a User (role Driver) into the operational driver roster.
     * Does not create orphan driver identities — source of truth is the user account.
     */
    public function storeDriver(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'phone' => ['nullable', 'string', 'max:50'],
        ], [
            'user_id.required' => 'Pilih akun Driver yang akan ditampilkan sebagai pengemudi.',
            'user_id.exists' => 'Akun Driver tidak ditemukan.',
        ]);

        $user = User::query()->findOrFail((int) $data['user_id']);
        if (! $user->hasRole(OperationalDriverDirectory::ROLE)) {
            throw ValidationException::withMessages([
                'user_id' => 'Akun yang dipilih tidak memiliki role Driver.',
            ]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => 'Akun Driver nonaktif tidak dapat ditambahkan ke roster penugasan.',
            ]);
        }

        $this->drivers->ensureForUser($user, $data['phone'] ?? null);

        return redirect()->route('vehicle-fleet.index')->with('success', 'Pengemudi disinkronkan dari akun Driver.');
    }

    public function updateDriver(Request $request, OperationalDriver $driver): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        if (! $driver->user_id) {
            // Legacy orphan row: keep limited phone/active edit for historical cleanup only.
            $legacy = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'is_active' => ['required', Rule::in(['0', '1'])],
            ], [
                'name.required' => 'Nama pengemudi wajib diisi.',
            ]);
            $driver->update([
                'name' => $legacy['name'],
                'phone' => $legacy['phone'] ?? null,
                'is_active' => $legacy['is_active'] === '1',
            ]);

            return redirect()->route('vehicle-fleet.index')->with('success', 'Data pengemudi lama diperbarui.');
        }

        $user = $driver->user()->firstOrFail();
        $this->drivers->ensureForUser($user, $data['phone'] ?? (string) ($user->phone ?? ''));

        return redirect()->route('vehicle-fleet.index')->with('success', 'Nomor telepon akun Driver diperbarui.');
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
