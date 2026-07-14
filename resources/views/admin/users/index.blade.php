@extends('layouts.app')

@section('title', 'Kelola Users - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Admin</li>
    <li class="breadcrumb-item active">Users</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-users me-2"></i>Kelola Users</h1>
    <button class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate"><i class="fas fa-plus me-1"></i>Tambah User</button>
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nama</th><th>Email</th><th>Telepon</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->phone ?? '-' }}</td>
                    <td>@foreach($user->roles as $role)<span class="badge bg-undira-blue me-1">{{ $role->name }}</span>@endforeach</td>
                    <td><span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        @if($user->id !== auth()->id())
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $user->id }}"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" data-confirm="Hapus user ini?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada user.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())<div class="card-footer">{{ $users->links() }}</div>@endif
</div>

<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.users.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control"></div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Role</label>
                        @foreach($roles as $role)
                        <div class="form-check"><input type="checkbox" name="roles[]" value="{{ $role->name }}" class="form-check-input" id="role{{ $role->id }}"><label class="form-check-label" for="role{{ $role->id }}">{{ $role->name }}</label></div>
                        @endforeach
                    </div>
                    <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked><label class="form-check-label">Aktif</label></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Simpan</button></div>
        </form>
    </div>
</div>

@foreach($users as $user)
<div class="modal fade" id="modalEdit{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $user->name }}" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ $user->email }}" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Password Baru</label><input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control" value="{{ $user->phone }}"></div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Role</label>
                        @foreach($roles as $role)
                        <div class="form-check"><input type="checkbox" name="roles[]" value="{{ $role->name }}" class="form-check-input" id="erole{{ $user->id }}_{{ $role->id }}" {{ $user->hasRole($role->name) ? 'checked' : '' }}><label class="form-check-label" for="erole{{ $user->id }}_{{ $role->id }}">{{ $role->name }}</label></div>
                        @endforeach
                    </div>
                    <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $user->is_active ? 'checked' : '' }}><label class="form-check-label">Aktif</label></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Perbarui</button></div>
        </form>
    </div>
</div>
@endforeach
@endsection
