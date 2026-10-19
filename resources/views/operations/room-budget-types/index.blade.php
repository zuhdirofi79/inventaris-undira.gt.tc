@extends('layouts.app')

@section('title', 'Master Anggaran Peminjaman Ruangan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Master Anggaran Peminjaman Ruangan"
    subtitle="Kelola pilihan dropdown Anggaran. Data yang sudah dipakai pengajuan tidak dihapus."
    icon="bi bi-wallet2"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Master Anggaran Peminjaman Ruangan'],
    ]"
/>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card card-undira mb-3">
            <div class="card-body">
                <h2 class="h6">Tambah jenis anggaran</h2>
                <form method="POST" action="{{ route('room-budget-types.store') }}" class="row g-2">
                    @csrf
                    <div class="col-md-8">
                        <input
                            name="name"
                            value="{{ old('name') }}"
                            class="form-control @error('name') is-invalid @enderror"
                            placeholder="Contoh: Anggaran Unit / Biro"
                            maxlength="255"
                            required
                        >
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-undira w-100" type="submit">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card card-undira">
            <div class="card-body">
                <h2 class="h6">Daftar jenis anggaran</h2>
                @forelse($budgetTypes as $type)
                    @php $isUsed = ($type->borrowings_count ?? 0) > 0; @endphp
                    <div class="row g-2 align-items-center border-bottom py-2">
                        <div class="col-md-5">
                            <form method="POST" action="{{ route('room-budget-types.update', $type) }}" id="update-budget-{{ $type->id }}">
                                @csrf
                                @method('PUT')
                                <input
                                    name="name"
                                    value="{{ old('name', $type->name) }}"
                                    class="form-control form-control-sm"
                                    maxlength="255"
                                    required
                                    form="update-budget-{{ $type->id }}"
                                >
                            </form>
                        </div>
                        <div class="col-md-3">
                            <select name="is_active" class="form-select form-select-sm" form="update-budget-{{ $type->id }}">
                                <option value="1" @selected($type->is_active)>Aktif</option>
                                <option value="0" @selected(! $type->is_active)>Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-outline-secondary w-100" type="submit" form="update-budget-{{ $type->id }}">Ubah</button>
                        </div>
                        <div class="col-md-2">
                            @if($isUsed)
                                <span class="badge text-bg-secondary w-100 py-2">Dipakai</span>
                            @else
                                <form
                                    method="POST"
                                    action="{{ route('room-budget-types.destroy', $type) }}"
                                    data-confirm="Hapus jenis anggaran ini? Tindakan tidak dapat dibatalkan."
                                    data-confirm-title="Hapus jenis anggaran"
                                    data-confirm-button="Ya, hapus"
                                    data-confirm-color="#dc3545"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger w-100" type="submit">Hapus</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada jenis anggaran. Tambahkan agar dropdown peminjaman ruangan memiliki pilihan.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
