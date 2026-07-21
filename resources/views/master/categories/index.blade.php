@extends('layouts.app')

@section('title', 'Master Data - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Master Data</li>
    <li class="breadcrumb-item active">Hub</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-database me-2"></i>Master Data</h1>
    <p class="text-muted mb-0 small">Single Source of Truth — kode master dikelola di sini, dropdown inventaris membaca data aktif.</p>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    @can('category.manage')
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'konfigurasi' ? 'active' : '' }}" href="{{ route('categories.index', ['tab' => 'konfigurasi']) }}">Konfigurasi Inventaris</a>
    </li>
    @endcan
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'klasifikasi' ? 'active' : '' }}" href="{{ route('categories.index', ['tab' => 'klasifikasi']) }}">Klasifikasi</a>
    </li>
    @can('unit.manage')
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'units' ? 'active' : '' }}" href="{{ route('categories.index', ['tab' => 'units']) }}">Unit / Divisi</a>
    </li>
    @endcan
    @can('procurement.manage')
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'pengadaan' ? 'active' : '' }}" href="{{ route('categories.index', ['tab' => 'pengadaan']) }}">Jenis Pengadaan</a>
    </li>
    @endcan
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'lokasi' ? 'active' : '' }}" href="{{ route('categories.index', ['tab' => 'lokasi']) }}">Lokasi & Lainnya</a>
    </li>
</ul>

@if($tab === 'konfigurasi')
@can('category.manage')
@include('master.categories._konfigurasi')
@else
<div class="alert alert-warning">Anda tidak memiliki izin mengelola konfigurasi inventaris.</div>
@endcan
@endif

@if($tab === 'klasifikasi')
@can('category.manage')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Klasifikasi (Kategori)</h5>
    <button class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateCategory">
        <i class="fas fa-plus me-1"></i>Tambah
    </button>
</div>
<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Slug</th><th>Sub</th><th>Inventaris</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($categories as $category)
                <tr>
                    <td><code>{{ $category->code }}</code></td>
                    <td><a href="{{ route('sub-categories.index', $category) }}">{{ $category->name }}</a></td>
                    <td><code>{{ $category->slug }}</code></td>
                    <td>{{ $category->sub_categories_count }}</td>
                    <td>{{ $category->inventory_items_count }}</td>
                    <td><span class="badge {{ $category->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditCategory{{ $category->id }}"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('categories.destroy', $category) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Data masih dapat dipulihkan dari menu Trash."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada klasifikasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($categories->hasPages())<div class="card-footer">{{ $categories->appends(['tab' => 'klasifikasi'])->links() }}</div>@endif
</div>

<div class="modal fade" id="modalCreateCategory" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('categories.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah Klasifikasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required maxlength="20" placeholder="ELK"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Simpan</button></div>
        </form>
    </div>
</div>
@foreach($categories as $category)
<div class="modal fade" id="modalEditCategory{{ $category->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('categories.update', $category) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit Klasifikasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="{{ $category->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $category->name }}" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ $category->description }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $category->is_active ? 'checked' : '' }}><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Perbarui</button></div>
        </form>
    </div>
</div>
@endforeach
@endcan
@endif

@if($tab === 'units')
@can('unit.manage')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Unit / Divisi</h5>
    <button class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateUnit">
        <i class="fas fa-plus me-1"></i>Tambah
    </button>
</div>
<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Inventaris</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($orgUnits as $unit)
                <tr>
                    <td><code>{{ $unit->code }}</code></td>
                    <td>{{ $unit->name }}</td>
                    <td>{{ $unit->inventory_items_count }}</td>
                    <td><span class="badge {{ $unit->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditUnit{{ $unit->id }}"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('org-units.destroy', $unit) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Data masih dapat dipulihkan dari menu Trash."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada unit/divisi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orgUnits->hasPages())<div class="card-footer">{{ $orgUnits->appends(['tab' => 'units'])->links() }}</div>@endif
</div>

<div class="modal fade" id="modalCreateUnit" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('org-units.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah Unit/Divisi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required maxlength="20" placeholder="PPPA"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Simpan</button></div>
        </form>
    </div>
</div>
@foreach($orgUnits as $unit)
<div class="modal fade" id="modalEditUnit{{ $unit->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('org-units.update', $unit) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit Unit/Divisi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="{{ $unit->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $unit->name }}" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ $unit->description }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $unit->is_active ? 'checked' : '' }}><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Perbarui</button></div>
        </form>
    </div>
</div>
@endforeach
@endcan
@endif

@if($tab === 'pengadaan')
@can('procurement.manage')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Jenis Pengadaan</h5>
    <button class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateProcurement">
        <i class="fas fa-plus me-1"></i>Tambah
    </button>
</div>
<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Inventaris</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($procurementTypes as $type)
                <tr>
                    <td><code>{{ $type->code }}</code></td>
                    <td>{{ $type->name }}</td>
                    <td>{{ $type->inventory_items_count }}</td>
                    <td><span class="badge {{ $type->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditProcurement{{ $type->id }}"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('procurement-types.destroy', $type) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Data masih dapat dipulihkan dari menu Trash."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada jenis pengadaan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($procurementTypes->hasPages())<div class="card-footer">{{ $procurementTypes->appends(['tab' => 'pengadaan'])->links() }}</div>@endif
</div>

<div class="modal fade" id="modalCreateProcurement" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('procurement-types.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah Jenis Pengadaan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required maxlength="20" placeholder="BOS"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Simpan</button></div>
        </form>
    </div>
</div>
@foreach($procurementTypes as $type)
<div class="modal fade" id="modalEditProcurement{{ $type->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('procurement-types.update', $type) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit Jenis Pengadaan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="{{ $type->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $type->name }}" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ $type->description }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $type->is_active ? 'checked' : '' }}><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Perbarui</button></div>
        </form>
    </div>
</div>
@endforeach
@endcan
@endif

@if($tab === 'lokasi')
<div class="row g-3">
    @can('location.manage')
    <div class="col-md-6 col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-map-marker-alt me-2"></i>Lokasi</h5>
                <p class="card-text small text-muted">Kampus → Gedung → Lantai → Ruangan (kode master lokasi).</p>
                <a href="{{ route('admin.locations.campuses.index') }}" class="btn btn-sm btn-undira">Kelola Lokasi</a>
            </div>
        </div>
    </div>
    @endcan
    @can('supplier.manage')
    <div class="col-md-6 col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-truck me-2"></i>Supplier</h5>
                <p class="card-text small text-muted">Master supplier untuk dropdown inventaris.</p>
                <a href="{{ route('suppliers.index') }}" class="btn btn-sm btn-outline-secondary">Kelola Supplier</a>
            </div>
        </div>
    </div>
    @endcan
    @can('vendor.manage')
    <div class="col-md-6 col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-store me-2"></i>Vendor</h5>
                <p class="card-text small text-muted">Master vendor untuk inventaris & maintenance.</p>
                <a href="{{ route('vendors.index') }}" class="btn btn-sm btn-outline-secondary">Kelola Vendor</a>
            </div>
        </div>
    </div>
    @endcan
    <div class="col-12">
        <div class="alert alert-info mb-0 small">
            <strong>Catatan:</strong> Kondisi &amp; Status inventaris tetap memakai enum sistem (stabil untuk alur operasi).
            Tahun pembelian tetap field numerik. Sub klasifikasi dikelola dari tautan nama klasifikasi.
        </div>
    </div>
</div>
@endif
@endsection
