@extends('layouts.app')

@section('title', 'Vendor - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Vendor"
    subtitle="Mitra perawatan dan layanan"
    icon="bi bi-shop"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Master Data', 'url' => route('categories.index')],
        ['label' => 'Vendor'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
>
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate" data-no-loader="1">
        <i class="bi bi-plus-lg me-1"></i>Tambah
    </button>
</x-page-header>

<x-master-table-toolbar
    :action="route('vendors.index')"
    placeholder="Nama / kontak / email / telepon…"
    :sort-options="['name' => 'Nama', 'email' => 'Email', 'created_at' => 'Tanggal dibuat']"
    :export-route="route('vendors.export', request()->only(['q', 'is_active', 'sort', 'dir']))"
    :import-route="route('vendors.import')"
    :template-route="route('vendors.import.template')"
/>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nama</th><th>Kontak</th><th>Email</th><th>Telepon</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($vendors as $vendor)
                <tr>
                    <td>{{ $vendor->name }}</td>
                    <td>{{ $vendor->contact_person ?? '-' }}</td>
                    <td>{{ $vendor->email ?? '-' }}</td>
                    <td>{{ $vendor->phone ?? '-' }}</td>
                    <td><span class="badge {{ $vendor->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $vendor->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $vendor->id }}" data-no-loader="1"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('vendors.destroy', $vendor) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Data masih dapat dipulihkan dari menu Trash."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada vendor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($vendors->hasPages())<div class="card-footer">{{ $vendors->links() }}</div>@endif
</div>
@endsection

@push('modals')
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('vendors.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah Vendor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Kontak Person</label><input type="text" name="contact_person" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="vendorCreateActive" checked>
                    <label class="form-check-label" for="vendorCreateActive">Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>

@foreach($vendors as $vendor)
<div class="modal fade" id="modalEdit{{ $vendor->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('vendors.update', $vendor) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit Vendor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $vendor->name }}" required></div>
                <div class="mb-3"><label class="form-label">Kontak Person</label><input type="text" name="contact_person" class="form-control" value="{{ $vendor->contact_person }}"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ $vendor->email }}"></div>
                <div class="mb-3"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control" value="{{ $vendor->phone }}"></div>
                <div class="mb-3"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2">{{ $vendor->address }}</textarea></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="vendorEditActive{{ $vendor->id }}" {{ $vendor->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="vendorEditActive{{ $vendor->id }}">Aktif</label>
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
