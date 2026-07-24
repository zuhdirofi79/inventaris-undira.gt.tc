@extends('layouts.app')

@section('title', 'Kelola Kampus - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Kelola Kampus"
    subtitle="Master lokasi kampus Universitas Dian Nusantara"
    icon="bi bi-building"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Master Data', 'url' => route('categories.index')],
        ['label' => 'Kampus'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
>
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate" data-no-loader="1">
        <i class="bi bi-plus-lg me-1"></i>Tambah
    </button>
</x-page-header>

<x-master-table-toolbar
    :action="route('admin.locations.campuses.index')"
    placeholder="Kode / nama / slug…"
    :sort-options="['name' => 'Nama', 'code' => 'Kode', 'created_at' => 'Tanggal dibuat']"
/>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Gedung</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($campuses as $campus)
                <tr>
                    <td><code>{{ $campus->code }}</code></td>
                    <td>
                        <a href="{{ route('admin.locations.buildings.index', $campus) }}">{{ $campus->name }}</a>
                    </td>
                    <td>{{ $campus->buildings_count }}</td>
                    <td><span class="badge {{ $campus->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $campus->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <a href="{{ route('admin.locations.buildings.index', $campus) }}" class="btn btn-sm btn-outline-primary" title="Kelola Gedung"><i class="fas fa-building"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $campus->id }}" data-no-loader="1"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('admin.locations.campuses.destroy', $campus) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Kampus beserta gedung/lantai/ruangan/inventaris turunannya akan masuk Trash dan masih dapat dipulihkan."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada kampus.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($campuses->hasPages())<div class="card-footer">{{ $campuses->links() }}</div>@endif
</div>
@endsection

@push('modals')
<div class="modal fade" id="modalCreate" tabindex="-1" aria-labelledby="modalCreateCampusLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.locations.campuses.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateCampusLabel">Tambah Kampus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="campusCreateActive" checked>
                    <label class="form-check-label" for="campusCreateActive">Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>

@foreach($campuses as $campus)
<div class="modal fade" id="modalEdit{{ $campus->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.locations.campuses.update', $campus) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Kampus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" value="{{ $campus->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ $campus->name }}" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ $campus->description }}</textarea></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="campusEditActive{{ $campus->id }}" {{ $campus->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="campusEditActive{{ $campus->id }}">Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endpush
