@extends('layouts.app')

@section('title', 'User Management - Inventaris UNDIRA')

@push('styles')
@include('partials.datatables-cdn-css')
@endpush

@section('content')
<x-page-header
    title="User Management"
    subtitle="Kelola akun, peran, status, dan password pengguna sistem"
    icon="bi bi-people"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Master Data'],
        ['label' => 'User Management'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
>
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate" data-no-loader="1">
        <i class="bi bi-plus-lg me-1"></i>Tambah User
    </button>
</x-page-header>

<div class="card card-undira mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label small mb-1" for="filterQ">Cari User</label>
                <input type="search" name="q" id="filterQ" value="{{ $search }}" class="form-control form-control-sm" placeholder="Nama atau email…">
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label small mb-1" for="filterRole">Filter Role</label>
                <select name="role" id="filterRole" class="form-select form-select-sm">
                    <option value="">Semua Role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" @selected($roleFilter === $role->name)>{{ \App\Support\RoleLabels::label($role->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <label class="form-label small mb-1" for="filterStatus">Filter Status</label>
                <select name="status" id="filterStatus" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label small mb-1" for="filterSort">Urutkan / Filter Pengguna</label>
                <select name="sort" id="filterSort" class="form-select form-select-sm">
                    <option value="" @selected($sort === '')>Default</option>
                    <option value="name_asc" @selected($sort === 'name_asc')>Nama A–Z</option>
                    <option value="name_desc" @selected($sort === 'name_desc')>Nama Z–A</option>
                    <option value="newest" @selected($sort === 'newest')>Pengguna Terbaru</option>
                    <option value="oldest" @selected($sort === 'oldest')>Pengguna Lama</option>
                    <option value="inactive_90" @selected($sort === 'inactive_90')>Aktif Lebih dari 90 Hari</option>
                    <option value="never_login" @selected($sort === 'never_login')>Belum Pernah Login</option>
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-undira btn-sm"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card card-undira">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 w-100" id="usersTable">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Default Password</th>
                        <th>Last Login</th>
                        <th>Tanggal Dibuat</th>
                        <th class="text-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    @php
                        $isPrimary = $user->isPrimarySuperAdmin();
                        $canUpdate = auth()->user()->can('update', $user);
                        $canDelete = auth()->user()->can('delete', $user);
                        $canChangeStatus = auth()->user()->can('changeStatus', $user);
                        $browser = \App\Support\UserAgentParser::browser($user->last_login_user_agent);
                        $device = $user->last_login_device ?: \App\Support\UserAgentParser::device($user->last_login_user_agent);
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $user->name }}</div>
                            @if($isPrimary)
                                <span class="badge bg-warning text-dark mt-1">
                                    <i class="fas fa-shield-alt me-1" aria-hidden="true"></i>Primary
                                </span>
                            @endif
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @forelse($user->roles as $role)
                                <span class="badge {{ \App\Support\RoleLabels::badgeClass($role->name) }} me-1">{{ \App\Support\RoleLabels::label($role->name) }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </td>
                        <td>
                            <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td>
                            @php $defaultDisplay = $user->defaultPasswordDisplay(); @endphp
                            @if($defaultDisplay === 'Sudah Diubah')
                                <span class="text-muted small">Sudah Diubah</span>
                            @else
                                <code class="small user-select-all">{{ $defaultDisplay }}</code>
                            @endif
                        </td>
                        <td>
                            @if($user->last_login_at)
                                <div class="small">{{ $user->last_login_at->timezone(config('app.timezone'))->format('d/m/Y') }}</div>
                                <div class="small text-muted">{{ $user->last_login_at->timezone(config('app.timezone'))->format('H:i') }} WIB</div>
                                @if($user->last_login_at->lt(now()->subDays(90)))
                                    <div class="small text-warning">Tidak login lebih dari 90 hari</div>
                                @endif
                                <div class="small text-muted" title="{{ $user->last_login_user_agent }}">
                                    {{ $browser }} · {{ $device }}
                                    @if($user->last_login_ip)
                                        <br>IP {{ $user->last_login_ip }}
                                    @endif
                                </div>
                            @else
                                <span class="text-muted small">Belum pernah</span>
                            @endif
                        </td>
                        <td data-order="{{ $user->created_at?->timestamp }}">
                            {{ $user->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        </td>
                        <td class="text-nowrap">
                            @if($canUpdate)
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $user->id }}" data-no-loader="1" title="Edit" aria-label="Edit {{ $user->name }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalReset{{ $user->id }}" data-no-loader="1" title="Reset Password" aria-label="Reset password {{ $user->name }}">
                                <i class="fas fa-key"></i>
                            </button>
                            @endif
                            @if($canChangeStatus && ! $isPrimary)
                            <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="d-inline" data-confirm="{{ $user->is_active ? 'Nonaktifkan user ini?' : 'Aktifkan user ini?' }}">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}" title="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" aria-label="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $user->name }}">
                                    <i class="fas {{ $user->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                </button>
                            </form>
                            @endif
                            @if($canDelete)
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" data-confirm="Hapus (soft delete) user ini?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus" aria-label="Hapus {{ $user->name }}"><i class="fas fa-trash"></i></button>
                            </form>
                            @elseif($isPrimary)
                            <span class="text-muted small ms-1" title="Akun utama sistem dilindungi."><i class="fas fa-lock"></i></span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.users.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Tambah User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        @foreach($roles as $role)
                            @if($role->name === 'super-admin' && ! auth()->user()->isPrimarySuperAdmin())
                                @continue
                            @endif
                            <div class="form-check">
                                <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="form-check-input" id="role{{ $role->id }}">
                                <label class="form-check-label" for="role{{ $role->id }}">
                                    <span class="badge {{ \App\Support\RoleLabels::badgeClass($role->name) }}">{{ \App\Support\RoleLabels::label($role->name) }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="createActive" checked>
                        <label class="form-check-label" for="createActive">Aktif</label>
                    </div>
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
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                @if($isPrimary)
                <div class="alert alert-warning small">
                    Akun <strong>Primary Super Admin</strong> dilindungi. Role dan status aktif tidak dapat diturunkan.
                </div>
                @endif
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="phone" class="form-control" value="{{ $user->phone }}">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Role</label>
                        @if($isPrimary || ! $canChangeRole)
                            @foreach($user->roles as $role)
                                <div><span class="badge {{ \App\Support\RoleLabels::badgeClass($role->name) }}">{{ \App\Support\RoleLabels::label($role->name) }}</span></div>
                                <input type="hidden" name="roles[]" value="{{ $role->name }}">
                            @endforeach
                            <p class="form-text mb-0">Role akun ini tidak dapat diubah dari formulir ini.</p>
                        @else
                            @foreach($roles as $role)
                                @if($role->name === 'super-admin' && ! auth()->user()->isPrimarySuperAdmin())
                                    @continue
                                @endif
                                <div class="form-check">
                                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="form-check-input" id="erole{{ $user->id }}_{{ $role->id }}" {{ $user->hasRole($role->name) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="erole{{ $user->id }}_{{ $role->id }}">
                                        <span class="badge {{ \App\Support\RoleLabels::badgeClass($role->name) }}">{{ \App\Support\RoleLabels::label($role->name) }}</span>
                                    </label>
                                </div>
                            @endforeach
                        @endif
                    </div>
                    @if($isPrimary || ! $canChangeStatus)
                        <input type="hidden" name="is_active" value="1">
                        <p class="small text-muted mb-0"><span class="badge bg-success">Aktif</span> — status dilindungi.</p>
                    @else
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="editActive{{ $user->id }}" {{ $user->is_active ? 'checked' : '' }}>
                            <label class="form-check-label" for="editActive{{ $user->id }}">Aktif</label>
                        </div>
                    @endif
                    <p class="form-text mt-3 mb-0">Untuk mengubah password, gunakan tombol <strong>Reset Password</strong>.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalReset{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.users.reset-password', $user) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Reset password untuk <strong>{{ $user->name }}</strong> ({{ $user->email }}).</p>
                <div class="mb-3">
                    <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                </div>
                <div class="form-check">
                    <input type="checkbox" name="must_change_password" value="1" class="form-check-input" id="mustChange{{ $user->id }}" checked>
                    <label class="form-check-label" for="mustChange{{ $user->id }}">
                        Wajib ganti password saat login berikutnya
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-key me-1"></i>Reset Password</button>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach
@endpush

@push('scripts')
@include('partials.datatables-cdn-js')
<script>
$(function () {
    @include('partials.datatables-id-language')
    try {
        $('#usersTable').DataTable({
            order: [],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            columnDefs: [
                { orderable: false, targets: [4, 7] }
            ],
            language: datatablesIdLanguage
        });
    } catch (err) {
        console.error('DataTables users i18n init failed, retrying without language:', err);
        try {
            $('#usersTable').DataTable({
            order: [],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            columnDefs: [
                { orderable: false, targets: [4, 7] }
            ]
        });
        } catch (err2) {
            console.error('DataTables users init failed:', err2);
        }
    }
});
</script>
@endpush
