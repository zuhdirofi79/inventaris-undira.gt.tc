@extends('layouts.app')

@section('title', 'Master Kendaraan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Master Kendaraan Operasional"
    subtitle="Kelola kendaraan dan pengemudi. Data yang sudah dipakai pengajuan tidak dihapus."
    icon="bi bi-truck"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Master Kendaraan'],
    ]"
/>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-undira mb-3">
            <div class="card-body">
                <h2 class="h6">Tambah kendaraan</h2>
                <form method="POST" action="{{ route('vehicle-fleet.vehicles.store') }}" class="row g-2">
                    @csrf
                    <div class="col-md-3">
                        <input name="name" value="{{ old('name') }}" class="form-control" placeholder="Nama" required>
                    </div>
                    <div class="col-md-2">
                        <input name="plate_number" value="{{ old('plate_number') }}" class="form-control" placeholder="Nomor polisi" required>
                    </div>
                    <div class="col-md-2">
                        <input name="category" value="{{ old('category') }}" class="form-control" placeholder="Jenis" required>
                    </div>
                    <div class="col-md-2">
                        <input name="passenger_capacity" value="{{ old('passenger_capacity') }}" class="form-control" inputmode="numeric" placeholder="Kapasitas">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-undira w-100" type="submit">Simpan</button>
                    </div>
                    @error('plate_number')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>
        <div class="card card-undira">
            <div class="card-body">
                <h2 class="h6">Kendaraan</h2>
                @forelse($vehicles as $vehicle)
                    <form method="POST" action="{{ route('vehicle-fleet.vehicles.update', $vehicle) }}" class="row g-2 align-items-center border-bottom py-2">
                        @csrf
                        @method('PUT')
                        <div class="col-md-3"><input name="name" value="{{ $vehicle->name }}" class="form-control form-control-sm" required></div>
                        <div class="col-md-2"><input name="plate_number" value="{{ $vehicle->plate_number }}" class="form-control form-control-sm" required></div>
                        <div class="col-md-2"><input name="category" value="{{ $vehicle->category }}" class="form-control form-control-sm" required></div>
                        <div class="col-md-1"><input name="passenger_capacity" value="{{ $vehicle->passenger_capacity }}" class="form-control form-control-sm" inputmode="numeric" placeholder="Kap."></div>
                        <div class="col-md-2">
                            <select name="is_active" class="form-select form-select-sm">
                                <option value="1" @selected($vehicle->is_active)>Aktif</option>
                                <option value="0" @selected(! $vehicle->is_active)>Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100" type="submit">Ubah</button></div>
                    </form>
                @empty
                    <p class="text-muted mb-0">Belum ada kendaraan.</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-undira mb-3">
            <div class="card-body">
                <h2 class="h6">Tambah rute</h2>
                <form method="POST" action="{{ route('vehicle-fleet.routes.store') }}" class="row g-2">
                    @csrf
                    <div class="col-md-8"><input name="name" class="form-control" placeholder="Nama rute" required></div>
                    <div class="col-md-4"><button class="btn btn-undira w-100" type="submit">Simpan</button></div>
                </form>
            </div>
        </div>
        <div class="card card-undira mb-3">
            <div class="card-body">
                <h2 class="h6">Rute</h2>
                @forelse($routes as $routeItem)
                    <form method="POST" action="{{ route('vehicle-fleet.routes.update', $routeItem) }}" class="row g-2 align-items-center border-bottom py-2">
                        @csrf
                        @method('PUT')
                        <div class="col-md-6"><input name="name" value="{{ $routeItem->name }}" class="form-control form-control-sm" required></div>
                        <div class="col-md-3">
                            <select name="is_active" class="form-select form-select-sm">
                                <option value="1" @selected($routeItem->is_active)>Aktif</option>
                                <option value="0" @selected(! $routeItem->is_active)>Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary w-100" type="submit">Ubah</button></div>
                    </form>
                @empty
                    <p class="text-muted mb-0">Belum ada rute. Tambahkan rute sebelum pengajuan kendaraan dapat memilihnya.</p>
                @endforelse
            </div>
        </div>
        <div class="card card-undira mb-3">
            <div class="card-body">
                <h2 class="h6">Tambah pengemudi</h2>
                <form method="POST" action="{{ route('vehicle-fleet.drivers.store') }}" class="row g-2">
                    @csrf
                    <div class="col-md-5"><input name="name" class="form-control" placeholder="Nama" required></div>
                    <div class="col-md-4"><input name="phone" class="form-control" placeholder="Telepon"></div>
                    <div class="col-md-3"><button class="btn btn-undira w-100" type="submit">Simpan</button></div>
                </form>
            </div>
        </div>
        <div class="card card-undira">
            <div class="card-body">
                <h2 class="h6">Pengemudi</h2>
                @forelse($drivers as $driver)
                    <form method="POST" action="{{ route('vehicle-fleet.drivers.update', $driver) }}" class="row g-2 align-items-center border-bottom py-2">
                        @csrf
                        @method('PUT')
                        <div class="col-md-4"><input name="name" value="{{ $driver->name }}" class="form-control form-control-sm" required></div>
                        <div class="col-md-3"><input name="phone" value="{{ $driver->phone }}" class="form-control form-control-sm"></div>
                        <div class="col-md-3">
                            <select name="is_active" class="form-select form-select-sm">
                                <option value="1" @selected($driver->is_active)>Aktif</option>
                                <option value="0" @selected(! $driver->is_active)>Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100" type="submit">Ubah</button></div>
                    </form>
                @empty
                    <p class="text-muted mb-0">Belum ada pengemudi.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
