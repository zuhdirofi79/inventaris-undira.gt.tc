@extends('layouts.app')

@section('title', 'Kelola Users - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Admin</li>
    <li class="breadcrumb-item active">Users</li>
@endsection

@section('content')
<x-ui.page-header title="Kelola Users" subtitle="Manajemen akun dan peran pengguna" icon="bi bi-people">
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate" data-no-loader="1">
        <i class="bi bi-plus-lg me-1"></i>Tambah User
    </button>
</x-ui.page-header>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nama</th><th>Email</th><th>Telepon</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($users as $user)
                @php
                    $isPrimary = $user->isPrimarySuperAdmin();
                    $canUpdate = auth()->user()->can('update', $user);
                    $canDelete = auth()->user()->can('delete', $user);
                    $canChangeRole = auth()->user()->can('changeRole', $user);
                    $canChangeStatus = auth()->user()->can('changeStatus', $user);
                @endphp
                <tr>
                    <td>
                        {{ $user->name }}
                        @if($isPrimary)
                            <span class="badge bg-warning text-dark ms-1" title="Akun utama sistem dan tidak dapat dihapus.">
                                <i class="fas fa-shield-alt me-1" aria-hidden="true"></i>Primary Super Admin
                            </span>
                        @endif
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->phone ?? '-' }}</td>
                    <td>
                        @foreach($user->roles as $role)
                            <span class="badge bg-undira-blue me-1">{{ \App\Support\RoleLabels::label($role->name) }}</span>
                        @endforeach
                    </td>
                    <td><span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        @if($canUpdate)
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $user->id }}" data-no-loader="1" aria-label="Edit {{ $user->name }}"><i class="fas fa-edit"></i></button>
                        @endif
                        @if($canDelete)
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" data-confirm="Hapus user ini?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus {{ $user->name }}"><i class="fas fa-trash"></i></button>
                        </form>
                        @elseif($isPrimary)
                        <span class="text-muted small" title="Akun utama sistem dan tidak dapat dihapus.">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                        </span>
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
@endsection

@push('modals')
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.users.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah User</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control"></div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Role</label>
                        @foreach($roles as $role)
                            @if($role->name === 'super-admin' && ! auth()->user()->isPrimarySuperAdmin())
                                @continue
                            @endif
                        <div class="form-check"><input type="checkbox" name="roles[]" value="{{ $role->name }}" class="form-check-input" id="role{{ $role->id }}"><label class="form-check-label" for="role{{ $role->id }}">{{ \App\Support\RoleLabels::label($role->name) }}</label></div>
                        @endforeach
                    </div>
                    <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="createActive" checked><label class="form-check-label" for="createActive">Aktif</label></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>

@foreach($users as $user)
@php
    $isPrimary = $user->isPrimarySuperAdmin();
    $canUpdate = auth()->user()->can('update', $user);
    $canChangeRole = auth()->user()->can('changeRole', $user);
    $canChangeStatus = auth()->user()->can('changeStatus', $user);
@endphp
@if($canUpdate)
<div class="modal fade" id="modalEdit{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                @if($isPrimary)
                <div class="alert alert-warning small">
                    Akun <strong>Primary Super Admin</strong> dilindungi. Role dan status aktif tidak dapat diturunkan.
                </div>
                @endif
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $user->name }}" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ $user->email }}" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Password Baru</label><input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control" value="{{ $user->phone }}"></div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Role</label>
                        @if($isPrimary || ! $canChangeRole)
                            @foreach($user->roles as $role)
                                <div><span class="badge bg-undira-blue">{{ \App\Support\RoleLabels::label($role->name) }}</span></div>
                                <input type="hidden" name="roles[]" value="{{ $role->name }}">
                            @endforeach
                            <p class="form-text mb-0">Role akun ini tidak dapat diubah dari formulir ini.</p>
                        @else
                            @foreach($roles as $role)
                                @if($role->name === 'super-admin' && ! auth()->user()->isPrimarySuperAdmin())
                                    @continue
                                @endif
                            <div class="form-check"><input type="checkbox" name="roles[]" value="{{ $role->name }}" class="form-check-input" id="erole{{ $user->id }}_{{ $role->id }}" {{ $user->hasRole($role->name) ? 'checked' : '' }}><label class="form-check-label" for="erole{{ $user->id }}_{{ $role->id }}">{{ \App\Support\RoleLabels::label($role->name) }}</label></div>
                            @endforeach
                        @endif
                    </div>
                    @if($isPrimary || ! $canChangeStatus)
                        <input type="hidden" name="is_active" value="1">
                        <p class="small text-muted mb-0"><span class="badge bg-success">Aktif</span> — status dilindungi.</p>
                    @else
                        <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="editActive{{ $user->id }}" {{ $user->is_active ? 'checked' : '' }}><label class="form-check-label" for="editActive{{ $user->id }}">Aktif</label></div>
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach
@endpush
