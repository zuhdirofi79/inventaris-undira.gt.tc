{{-- Lokasi & Lainnya: hub cards → canonical modules; supporting masters CRUD inline --}}
@php
    $hub = $lokasiHub ?? [];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">Lokasi dan Master Pendukung</h5>
        <p class="text-muted small mb-0">Hirarki lokasi dikelola di modul kanonis. Fungsi ruang, tahun pembelian, dan kondisi dikelola di bawah.</p>
    </div>
</div>

<h6 class="text-uppercase text-muted small fw-semibold mb-2">A. Lokasi</h6>
<div class="row g-3 mb-4">
    @can('location.manage')
    <div class="col-md-6 col-lg-3">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h6 class="card-title mb-1"><i class="fas fa-university me-1 text-primary"></i>Kampus</h6>
                <p class="small text-muted mb-2">{{ $hub['campuses_active'] ?? 0 }} aktif / {{ $hub['campuses_total'] ?? 0 }} total</p>
                <a href="{{ route('admin.locations.campuses.index') }}" class="btn btn-sm btn-undira">Kelola Kampus</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h6 class="card-title mb-1"><i class="fas fa-building me-1 text-primary"></i>Gedung</h6>
                <p class="small text-muted mb-2">{{ $hub['buildings_active'] ?? 0 }} aktif / {{ $hub['buildings_total'] ?? 0 }} total</p>
                <a href="{{ route('admin.locations.campuses.index') }}" class="btn btn-sm btn-outline-secondary">Kelola Gedung</a>
                <p class="small text-muted mt-2 mb-0">Buka kampus → pilih gedung.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h6 class="card-title mb-1"><i class="fas fa-layer-group me-1 text-primary"></i>Lantai</h6>
                <p class="small text-muted mb-2">{{ $hub['floors_active'] ?? 0 }} aktif / {{ $hub['floors_total'] ?? 0 }} total</p>
                <a href="{{ route('admin.locations.campuses.index') }}" class="btn btn-sm btn-outline-secondary">Kelola Lantai</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h6 class="card-title mb-1"><i class="fas fa-door-open me-1 text-primary"></i>Ruangan</h6>
                <p class="small text-muted mb-2">{{ $hub['rooms_active'] ?? 0 }} aktif / {{ $hub['rooms_total'] ?? 0 }} total</p>
                <a href="{{ route('admin.locations.campuses.index') }}" class="btn btn-sm btn-outline-secondary">Kelola Ruangan</a>
            </div>
        </div>
    </div>
    @else
    <div class="col-12"><div class="alert alert-light border small mb-0">Anda tidak memiliki izin mengelola hirarki lokasi.</div></div>
    @endcan
</div>

<h6 class="text-uppercase text-muted small fw-semibold mb-2">B. Master Pendukung</h6>
<div class="row g-3 mb-4">
    @can('category.manage')
    <div class="col-md-4">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h6 class="card-title mb-1"><i class="fas fa-th-large me-1"></i>Fungsi Ruang</h6>
                <p class="small text-muted mb-2">{{ $hub['room_functions_active'] ?? 0 }} aktif / {{ $hub['room_functions_total'] ?? 0 }} total</p>
                <a href="#section-fungsi-ruang" class="btn btn-sm btn-undira" data-no-loader="1">Kelola Fungsi Ruang</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h6 class="card-title mb-1"><i class="fas fa-calendar-alt me-1"></i>Tahun Pembelian</h6>
                <p class="small text-muted mb-2">{{ $hub['purchase_years_active'] ?? 0 }} aktif / {{ $hub['purchase_years_total'] ?? 0 }} total</p>
                <a href="#section-tahun-pembelian" class="btn btn-sm btn-undira" data-no-loader="1">Kelola Tahun Pembelian</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h6 class="card-title mb-1"><i class="fas fa-heartbeat me-1"></i>Kondisi</h6>
                <p class="small text-muted mb-2">{{ $hub['conditions_active'] ?? 0 }} aktif / {{ $hub['conditions_total'] ?? 0 }} total</p>
                <a href="#section-kondisi" class="btn btn-sm btn-undira" data-no-loader="1">Kelola Kondisi</a>
            </div>
        </div>
    </div>
    @else
    <div class="col-12"><div class="alert alert-light border small mb-0">Master pendukung (fungsi ruang, tahun, kondisi) memerlukan izin category.manage.</div></div>
    @endcan

    @can('supplier.manage')
    <div class="col-md-6 col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h6 class="card-title mb-1"><i class="fas fa-truck me-1"></i>Supplier</h6>
                <p class="small text-muted mb-2">Master supplier inventaris.</p>
                <a href="{{ route('suppliers.index') }}" class="btn btn-sm btn-outline-secondary">Kelola Supplier</a>
            </div>
        </div>
    </div>
    @endcan
    @can('vendor.manage')
    <div class="col-md-6 col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-body">
                <h6 class="card-title mb-1"><i class="fas fa-store me-1"></i>Vendor</h6>
                <p class="small text-muted mb-2">Master vendor inventaris &amp; maintenance.</p>
                <a href="{{ route('vendors.index') }}" class="btn btn-sm btn-outline-secondary">Kelola Vendor</a>
            </div>
        </div>
    </div>
    @endcan
</div>

@can('category.manage')
{{-- Fungsi Ruang --}}
<div class="card card-undira mb-3" id="section-fungsi-ruang">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Fungsi Ruang</strong>
        <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateRoomFunction" data-no-loader="1">
            <i class="fas fa-plus me-1"></i>Tambah Fungsi Ruang
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-sm">
                <thead><tr><th>Kode</th><th>Nama</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse(($roomFunctions ?? []) as $fn)
                    <tr>
                        <td><code>{{ $fn->code }}</code></td>
                        <td>{{ $fn->name }}</td>
                        <td>{{ $fn->sort_order }}</td>
                        <td><span class="badge {{ $fn->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $fn->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditRoomFunction{{ $fn->id }}" data-no-loader="1" aria-label="Edit {{ $fn->name }}"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('room-functions.destroy', $fn) }}" method="POST" class="d-inline"
                                  data-confirm-title="Hapus fungsi ruang?"
                                  data-confirm="Jika sudah dipakai konfigurasi, data akan dinonaktifkan."
                                  data-confirm-button="Lanjutkan">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus {{ $fn->name }}"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada fungsi ruang.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(($roomFunctions ?? null)?->hasPages())
    <div class="card-footer">{{ $roomFunctions->appends(request()->except('functions_page'))->links() }}</div>
    @endif
</div>

{{-- Tahun Pembelian --}}
<div class="card card-undira mb-3" id="section-tahun-pembelian">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Tahun Pembelian</strong>
        <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreatePurchaseYear" data-no-loader="1">
            <i class="fas fa-plus me-1"></i>Tambah Tahun
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-sm">
                <thead><tr><th>Tahun</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse(($purchaseYears ?? []) as $year)
                    <tr>
                        <td><strong>{{ $year->year }}</strong></td>
                        <td><span class="badge {{ $year->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $year->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditPurchaseYear{{ $year->id }}" data-no-loader="1" aria-label="Edit tahun {{ $year->year }}"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('purchase-years.destroy', $year) }}" method="POST" class="d-inline"
                                  data-confirm-title="Hapus tahun?"
                                  data-confirm="Jika sudah dipakai konfigurasi, tahun akan dinonaktifkan."
                                  data-confirm-button="Lanjutkan">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus tahun {{ $year->year }}"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center text-muted py-4">Belum ada tahun pembelian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(($purchaseYears ?? null)?->hasPages())
    <div class="card-footer">{{ $purchaseYears->appends(request()->except('years_page'))->links() }}</div>
    @endif
</div>

{{-- Kondisi --}}
<div class="card card-undira mb-3" id="section-kondisi">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Kondisi Aset</strong>
        <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateCondition" data-no-loader="1">
            <i class="fas fa-plus me-1"></i>Tambah Kondisi
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-sm">
                <thead><tr><th>Kode</th><th>Nama</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse(($inventoryConditions ?? []) as $cond)
                    <tr>
                        <td><code>{{ $cond->code }}</code></td>
                        <td>{{ $cond->name }}</td>
                        <td>{{ $cond->sort_order }}</td>
                        <td><span class="badge {{ $cond->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $cond->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditCondition{{ $cond->id }}" data-no-loader="1" aria-label="Edit {{ $cond->name }}"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('inventory-conditions.destroy', $cond) }}" method="POST" class="d-inline"
                                  data-confirm-title="Hapus kondisi?"
                                  data-confirm="Jika sudah dipakai konfigurasi, kondisi akan dinonaktifkan."
                                  data-confirm-button="Lanjutkan">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus {{ $cond->name }}"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada master kondisi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(($inventoryConditions ?? null)?->hasPages())
    <div class="card-footer">{{ $inventoryConditions->appends(request()->except('conditions_page'))->links() }}</div>
    @endif
</div>

<div class="alert alert-info small mb-0">
    <strong>Catatan:</strong> Kondisi di sini adalah kondisi fisik aset (Baik, Layak, Rusak), berbeda dari status operasional inventaris.
    Nilai aktif langsung muncul di dropdown Konfigurasi Inventaris.
</div>
@endcan
