@extends('layouts.app')

@section('title', 'Kelola Lantai - ' . $building->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.locations.campuses.index') }}">Kelola Kampus</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.locations.buildings.index', $building->campus) }}">{{ $building->campus->name }}</a></li>
    <li class="breadcrumb-item active">{{ $building->name }}</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-layer-group me-2"></i>Lantai: {{ $building->name }}</h1>
    <button class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate"><i class="fas fa-plus me-1"></i>Tambah Lantai</button>
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>No. Lantai</th><th>Nama</th><th>Ruangan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($floors as $floor)
                <tr>
                    <td>{{ $floor->floor_number }}</td>
                    <td><a href="{{ route('admin.locations.rooms.index', $floor) }}">{{ $floor->name }}</a></td>
                    <td>{{ $floor->rooms_count }}</td>
                    <td><span class="badge {{ $floor->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $floor->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <a href="{{ route('admin.locations.rooms.index', $floor) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-door-open"></i></a>
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $floor->id }}"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('admin.locations.floors.destroy', $floor) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Lantai beserta ruangan/inventaris turunannya akan masuk Trash dan masih dapat dipulihkan."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada lantai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($floors->hasPages())<div class="card-footer">{{ $floors->links() }}</div>@endif
</div>

<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.locations.floors.store') }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="building_id" value="{{ $building->id }}">
            <div class="modal-header"><h5 class="modal-title">Tambah Lantai</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required maxlength="20" placeholder="L1"></div>
                <div class="mb-3"><label class="form-label">No. Lantai</label><input type="number" name="floor_number" class="form-control" required min="0"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required placeholder="Lantai 1"></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                    <label class="form-check-label">Aktif</label>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Simpan</button></div>
        </form>
    </div>
</div>

@foreach($floors as $floor)
<div class="modal fade" id="modalEdit{{ $floor->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.locations.floors.update', $floor) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit Lantai</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="{{ $floor->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">No. Lantai</label><input type="number" name="floor_number" class="form-control" value="{{ $floor->floor_number }}" required min="0"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $floor->name }}" required></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $floor->is_active ? 'checked' : '' }}>
                    <label class="form-check-label">Aktif</label>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Perbarui</button></div>
        </form>
    </div>
</div>
@endforeach
@endsection
