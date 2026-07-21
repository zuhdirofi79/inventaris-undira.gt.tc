@extends('layouts.app')

@section('title', 'Kelola Gedung - ' . $campus->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.locations.campuses.index') }}">Kelola Kampus</a></li>
    <li class="breadcrumb-item active">{{ $campus->name }}</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-building me-2"></i>Gedung: {{ $campus->name }}</h1>
    </div>
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate" data-no-loader="1">
        <i class="fas fa-plus me-1"></i>Tambah Gedung
    </button>
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Lantai</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($buildings as $building)
                <tr>
                    <td><code>{{ $building->code }}</code></td>
                    <td><a href="{{ route('admin.locations.floors.index', $building) }}">{{ $building->name }}</a></td>
                    <td>{{ $building->floors_count }}</td>
                    <td><span class="badge {{ $building->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $building->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <a href="{{ route('admin.locations.floors.index', $building) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-layer-group"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $building->id }}" data-no-loader="1"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('admin.locations.buildings.destroy', $building) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Gedung beserta lantai/ruangan/inventaris turunannya akan masuk Trash dan masih dapat dipulihkan."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada gedung.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($buildings->hasPages())<div class="card-footer">{{ $buildings->links() }}</div>@endif
</div>
@endsection

@push('modals')
<div class="modal fade" id="modalCreate" tabindex="-1" aria-labelledby="modalCreateBuildingLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.locations.buildings.store') }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="campus_id" value="{{ $campus->id }}">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateBuildingLabel">Tambah Gedung</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="buildingCreateActive" checked>
                    <label class="form-check-label" for="buildingCreateActive">Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>

@foreach($buildings as $building)
<div class="modal fade" id="modalEdit{{ $building->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.locations.buildings.update', $building) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Gedung</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" value="{{ $building->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ $building->name }}" required></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="buildingEditActive{{ $building->id }}" {{ $building->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="buildingEditActive{{ $building->id }}">Aktif</label>
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
