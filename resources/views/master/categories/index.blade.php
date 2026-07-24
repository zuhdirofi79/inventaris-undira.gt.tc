@extends('layouts.app')

@section('title', 'Master Data - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Master Data"
    subtitle="Single Source of Truth — kode master dikelola di sini, dropdown inventaris membaca data aktif."
    icon="bi bi-database"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Master Data'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
/>

<nav class="master-tabs-wrap mb-3" aria-label="Tab Master Data">
    <ul class="nav nav-tabs master-tabs flex-nowrap" role="tablist">
        @canany(['category.manage', 'inventory.create'])
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'konfigurasi' ? 'active' : '' }}"
               href="{{ route('categories.index', ['tab' => 'konfigurasi']) }}"
               @if($tab === 'konfigurasi') aria-current="page" @endif
               data-no-loader="1">Konfigurasi Inventaris</a>
        </li>
        @endcanany
        @can('category.manage')
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'klasifikasi' ? 'active' : '' }}"
               href="{{ route('categories.index', ['tab' => 'klasifikasi']) }}"
               @if($tab === 'klasifikasi') aria-current="page" @endif
               data-no-loader="1">Klasifikasi</a>
        </li>
        @endcan
        @can('unit.manage')
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'units' ? 'active' : '' }}"
               href="{{ route('categories.index', ['tab' => 'units']) }}"
               @if($tab === 'units') aria-current="page" @endif
               data-no-loader="1">Unit / Divisi</a>
        </li>
        @endcan
        @can('procurement.manage')
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'pengadaan' ? 'active' : '' }}"
               href="{{ route('categories.index', ['tab' => 'pengadaan']) }}"
               @if($tab === 'pengadaan') aria-current="page" @endif
               data-no-loader="1">Jenis Pengadaan</a>
        </li>
        @endcan
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'lokasi' ? 'active' : '' }}"
               href="{{ route('categories.index', ['tab' => 'lokasi']) }}"
               @if($tab === 'lokasi') aria-current="page" @endif
               data-no-loader="1">Lokasi & Lainnya</a>
        </li>
    </ul>
</nav>

@if($tab === 'konfigurasi')
@canany(['category.manage', 'inventory.create'])
@include('master.categories._konfigurasi')
@else
<div class="alert alert-warning">Anda tidak memiliki izin mengelola konfigurasi inventaris.</div>
@endcanany
@endif

@if($tab === 'klasifikasi')
@can('category.manage')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">Klasifikasi Inventaris</h5>
        <p class="text-muted small mb-0">Kategori inventaris (Furniture, Elektronik, …). Subklasifikasi dikelola dari tautan nama.</p>
    </div>
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateCategory" data-no-loader="1">
        <i class="fas fa-plus me-1"></i>Tambah Klasifikasi
    </button>
</div>
<x-master-table-toolbar
    :action="route('categories.index')"
    placeholder="Kode / nama / slug…"
    :sort-options="['name' => 'Nama', 'code' => 'Kode', 'created_at' => 'Tanggal dibuat']"
    :export-route="route('categories.export', request()->only(['q', 'is_active', 'sort', 'dir']))"
    :import-route="route('categories.import')"
    :template-route="route('categories.import.template')"
    :hidden="['tab' => 'klasifikasi']"
/>
<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
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
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditCategory{{ $category->id }}" data-no-loader="1" aria-label="Edit {{ $category->name }}"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('categories.destroy', $category) }}" method="POST" class="d-inline"
                                  data-confirm-title="Pindahkan data ke Trash?"
                                  data-confirm="Data masih dapat dipulihkan dari menu Trash."
                                  data-confirm-button="Pindahkan ke Trash">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus {{ $category->name }}"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <p class="mb-2">Belum ada klasifikasi.</p>
                            <p class="small mb-3">Tambahkan klasifikasi agar dapat digunakan pada Konfigurasi Inventaris.</p>
                            <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateCategory" data-no-loader="1">
                                <i class="fas fa-plus me-1"></i>Tambah Klasifikasi
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($categories->hasPages())<div class="card-footer">{{ $categories->appends(request()->except('categories_page'))->links() }}</div>@endif
</div>
@else
<div class="alert alert-warning">Anda tidak memiliki izin mengelola klasifikasi.</div>
@endcan
@endif

@if($tab === 'units')
@can('unit.manage')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">Unit / Divisi</h5>
        <p class="text-muted small mb-0">Unit organisasi (PPPA, IT, BOP, …) untuk dropdown Konfigurasi Inventaris.</p>
    </div>
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateUnit" data-no-loader="1">
        <i class="fas fa-plus me-1"></i>Tambah Unit
    </button>
</div>
<x-master-table-toolbar
    :action="route('categories.index')"
    placeholder="Kode / nama…"
    :sort-options="['name' => 'Nama', 'code' => 'Kode', 'created_at' => 'Tanggal dibuat']"
    :hidden="['tab' => 'units']"
/>
<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Kode</th><th>Nama</th><th>Penggunaan</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse($orgUnits as $unit)
                    <tr>
                        <td><code>{{ $unit->code }}</code></td>
                        <td>{{ $unit->name }}</td>
                        <td>{{ $unit->inventory_items_count }}</td>
                        <td><span class="badge {{ $unit->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditUnit{{ $unit->id }}" data-no-loader="1" aria-label="Edit {{ $unit->name }}"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('org-units.destroy', $unit) }}" method="POST" class="d-inline"
                                  data-confirm-title="Pindahkan data ke Trash?"
                                  data-confirm="Data masih dapat dipulihkan dari menu Trash."
                                  data-confirm-button="Pindahkan ke Trash">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus {{ $unit->name }}"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <p class="mb-2">Belum ada Unit / Divisi.</p>
                            <p class="small mb-3">Tambahkan Unit agar dapat digunakan pada Konfigurasi Inventaris.</p>
                            <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateUnit" data-no-loader="1">
                                <i class="fas fa-plus me-1"></i>Tambah Unit
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($orgUnits->hasPages())<div class="card-footer">{{ $orgUnits->appends(request()->except('units_page'))->links() }}</div>@endif
</div>
@else
<div class="alert alert-warning">Anda tidak memiliki izin mengelola unit/divisi.</div>
@endcan
@endif

@if($tab === 'pengadaan')
@can('procurement.manage')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">Jenis Pengadaan</h5>
        <p class="text-muted small mb-0">Sumber pengadaan (UNDIRA, Hibah, …) untuk dropdown Konfigurasi Inventaris.</p>
    </div>
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateProcurement" data-no-loader="1">
        <i class="fas fa-plus me-1"></i>Tambah Jenis Pengadaan
    </button>
</div>
<x-master-table-toolbar
    :action="route('categories.index')"
    placeholder="Kode / nama…"
    :sort-options="['name' => 'Nama', 'code' => 'Kode', 'created_at' => 'Tanggal dibuat']"
    :hidden="['tab' => 'pengadaan']"
/>
<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Kode</th><th>Nama</th><th>Penggunaan</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse($procurementTypes as $type)
                    <tr>
                        <td><code>{{ $type->code }}</code></td>
                        <td>{{ $type->name }}</td>
                        <td>{{ $type->inventory_items_count }}</td>
                        <td><span class="badge {{ $type->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditProcurement{{ $type->id }}" data-no-loader="1" aria-label="Edit {{ $type->name }}"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('procurement-types.destroy', $type) }}" method="POST" class="d-inline"
                                  data-confirm-title="Pindahkan data ke Trash?"
                                  data-confirm="Data masih dapat dipulihkan dari menu Trash."
                                  data-confirm-button="Pindahkan ke Trash">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus {{ $type->name }}"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <p class="mb-2">Belum ada jenis pengadaan.</p>
                            <p class="small mb-3">Tambahkan jenis pengadaan agar tersedia di Konfigurasi Inventaris.</p>
                            <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateProcurement" data-no-loader="1">
                                <i class="fas fa-plus me-1"></i>Tambah Jenis Pengadaan
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($procurementTypes->hasPages())<div class="card-footer">{{ $procurementTypes->appends(request()->except('procurement_page'))->links() }}</div>@endif
</div>
@else
<div class="alert alert-warning">Anda tidak memiliki izin mengelola jenis pengadaan.</div>
@endcan
@endif

@if($tab === 'lokasi')
@include('master.categories._lokasi')
@endif
@endsection

@push('modals')
@if($tab === 'klasifikasi')
@can('category.manage')
<div class="modal fade" id="modalCreateCategory" tabindex="-1" aria-labelledby="modalCreateCategoryLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('categories.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateCategoryLabel">Tambah Klasifikasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="catCreateCode">Kode <span class="text-danger">*</span></label><input type="text" name="code" id="catCreateCode" class="form-control" required maxlength="20" placeholder="ELK" value="{{ old('code') }}"></div>
                <div class="mb-3"><label class="form-label" for="catCreateName">Nama <span class="text-danger">*</span></label><input type="text" name="name" id="catCreateName" class="form-control" required value="{{ old('name') }}"></div>
                <div class="mb-3"><label class="form-label" for="catCreateDesc">Deskripsi</label><textarea name="description" id="catCreateDesc" class="form-control" rows="2">{{ old('description') }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="categoryCreateActive" @checked(old('is_active', '1'))><label class="form-check-label" for="categoryCreateActive">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>
@foreach($categories as $category)
<div class="modal fade" id="modalEditCategory{{ $category->id }}" tabindex="-1" aria-labelledby="modalEditCategoryLabel{{ $category->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('categories.update', $category) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditCategoryLabel{{ $category->id }}">Edit Klasifikasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" value="{{ $category->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ $category->name }}" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ $category->description }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="categoryEditActive{{ $category->id }}" {{ $category->is_active ? 'checked' : '' }}><label class="form-check-label" for="categoryEditActive{{ $category->id }}">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endcan
@endif

@if($tab === 'units')
@can('unit.manage')
<div class="modal fade" id="modalCreateUnit" tabindex="-1" aria-labelledby="modalCreateUnitLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('org-units.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateUnitLabel">Tambah Unit / Divisi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="unitCreateCode">Kode <span class="text-danger">*</span></label><input type="text" name="code" id="unitCreateCode" class="form-control" required maxlength="20" placeholder="PPPA" value="{{ old('code') }}"></div>
                <div class="mb-3"><label class="form-label" for="unitCreateName">Nama <span class="text-danger">*</span></label><input type="text" name="name" id="unitCreateName" class="form-control" required value="{{ old('name') }}"></div>
                <div class="mb-3"><label class="form-label" for="unitCreateDesc">Deskripsi</label><textarea name="description" id="unitCreateDesc" class="form-control" rows="2">{{ old('description') }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="unitCreateActive" @checked(old('is_active', '1'))><label class="form-check-label" for="unitCreateActive">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>
@foreach($orgUnits as $unit)
<div class="modal fade" id="modalEditUnit{{ $unit->id }}" tabindex="-1" aria-labelledby="modalEditUnitLabel{{ $unit->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('org-units.update', $unit) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditUnitLabel{{ $unit->id }}">Edit Unit / Divisi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" value="{{ $unit->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ $unit->name }}" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ $unit->description }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="unitEditActive{{ $unit->id }}" {{ $unit->is_active ? 'checked' : '' }}><label class="form-check-label" for="unitEditActive{{ $unit->id }}">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endcan
@endif

@if($tab === 'pengadaan')
@can('procurement.manage')
<div class="modal fade" id="modalCreateProcurement" tabindex="-1" aria-labelledby="modalCreateProcurementLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('procurement-types.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateProcurementLabel">Tambah Jenis Pengadaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="procCreateCode">Kode <span class="text-danger">*</span></label><input type="text" name="code" id="procCreateCode" class="form-control" required maxlength="20" placeholder="HIBAH" value="{{ old('code') }}"></div>
                <div class="mb-3"><label class="form-label" for="procCreateName">Nama <span class="text-danger">*</span></label><input type="text" name="name" id="procCreateName" class="form-control" required value="{{ old('name') }}"></div>
                <div class="mb-3"><label class="form-label" for="procCreateDesc">Deskripsi</label><textarea name="description" id="procCreateDesc" class="form-control" rows="2">{{ old('description') }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="procurementCreateActive" @checked(old('is_active', '1'))><label class="form-check-label" for="procurementCreateActive">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>
@foreach($procurementTypes as $type)
<div class="modal fade" id="modalEditProcurement{{ $type->id }}" tabindex="-1" aria-labelledby="modalEditProcurementLabel{{ $type->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('procurement-types.update', $type) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditProcurementLabel{{ $type->id }}">Edit Jenis Pengadaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" value="{{ $type->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ $type->name }}" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ $type->description }}</textarea></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="procurementEditActive{{ $type->id }}" {{ $type->is_active ? 'checked' : '' }}><label class="form-check-label" for="procurementEditActive{{ $type->id }}">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endcan
@endif

@if($tab === 'lokasi')
@include('master.categories._lokasi-modals')
@endif
@endpush
