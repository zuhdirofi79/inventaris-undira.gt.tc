@extends('layouts.app')

@section('title', 'Kelola Kampus - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Admin</li>
    <li class="breadcrumb-item active">Kelola Kampus</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-university me-2"></i>Kelola Kampus</h1>
    <button class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate"><i class="fas fa-plus me-1"></i>Tambah</button>
</div>

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
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $campus->id }}"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('admin.locations.campuses.destroy', $campus) }}" method="POST" class="d-inline" data-confirm="Hapus kampus?">
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

<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.locations.campuses.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah Kampus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Simpan</button></div>
        </form>
    </div>
</div>

@foreach($campuses as $campus)
<div class="modal fade" id="modalEdit{{ $campus->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.locations.campuses.update', $campus) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit Kampus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="{{ $campus->code }}" required></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $campus->name }}" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ $campus->description }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $campus->is_active ? 'checked' : '' }}><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Perbarui</button></div>
        </form>
    </div>
</div>
@endforeach
@endsection
