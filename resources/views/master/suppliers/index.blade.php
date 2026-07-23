@extends('layouts.app')

@section('title', 'Supplier - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Master Data</li>
    <li class="breadcrumb-item active">Supplier</li>
@endsection

@section('content')
<x-ui.page-header title="Supplier" subtitle="Mitra pengadaan inventaris" icon="bi bi-truck">
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate" data-no-loader="1">
        <i class="bi bi-plus-lg me-1"></i>Tambah
    </button>
</x-ui.page-header>

<x-master-table-toolbar
    :action="route('suppliers.index')"
    placeholder="Nama / kontak / email / telepon…"
    :sort-options="['name' => 'Nama', 'email' => 'Email', 'created_at' => 'Tanggal dibuat']"
    :export-route="route('suppliers.export', request()->only(['q', 'is_active', 'sort', 'dir']))"
    :import-route="route('suppliers.import')"
    :template-route="route('suppliers.import.template')"
/>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nama</th><th>Kontak</th><th>Email</th><th>Telepon</th><th>Inventaris</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($suppliers as $supplier)
                <tr>
                    <td>{{ $supplier->name }}</td>
                    <td>{{ $supplier->contact_person ?? '-' }}</td>
                    <td>{{ $supplier->email ?? '-' }}</td>
                    <td>{{ $supplier->phone ?? '-' }}</td>
                    <td>{{ $supplier->inventory_items_count }}</td>
                    <td><span class="badge {{ $supplier->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $supplier->id }}" data-no-loader="1"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Data masih dapat dipulihkan dari menu Trash."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada supplier.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($suppliers->hasPages())<div class="card-footer">{{ $suppliers->links() }}</div>@endif
</div>
@endsection

@push('modals')
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('suppliers.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Kontak Person</label><input type="text" name="contact_person" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="supplierCreateActive" checked>
                    <label class="form-check-label" for="supplierCreateActive">Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>

@foreach($suppliers as $supplier)
<div class="modal fade" id="modalEdit{{ $supplier->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('suppliers.update', $supplier) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $supplier->name }}" required></div>
                <div class="mb-3"><label class="form-label">Kontak Person</label><input type="text" name="contact_person" class="form-control" value="{{ $supplier->contact_person }}"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ $supplier->email }}"></div>
                <div class="mb-3"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control" value="{{ $supplier->phone }}"></div>
                <div class="mb-3"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2">{{ $supplier->address }}</textarea></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="supplierEditActive{{ $supplier->id }}" {{ $supplier->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="supplierEditActive{{ $supplier->id }}">Aktif</label>
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
