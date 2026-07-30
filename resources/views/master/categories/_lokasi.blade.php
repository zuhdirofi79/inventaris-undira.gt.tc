{{-- Hub Master Data: dashboard referensi + quick access (CRUD kanonis tidak diubah). --}}
@php
    use App\Enums\InventoryStatus;
    use App\Models\Category;
    use App\Models\OrgUnit;
    use App\Models\ProcurementType;
    use App\Models\SubCategory;
    use App\Models\Supplier;
    use App\Models\Vendor;

    $hub = $lokasiHub ?? [];
    // Count tambahan via model existing (tanpa ubah controller/route).
    $hub['units_total'] = OrgUnit::query()->count();
    $hub['units_active'] = OrgUnit::query()->where('is_active', true)->count();
    $hub['categories_total'] = Category::query()->count();
    $hub['categories_active'] = Category::query()->where('is_active', true)->count();
    $hub['sub_categories_total'] = SubCategory::query()->count();
    $hub['sub_categories_active'] = SubCategory::query()->where('is_active', true)->count();
    $hub['vendors_total'] = Vendor::query()->count();
    $hub['vendors_active'] = Vendor::query()->where('is_active', true)->count();
    $hub['suppliers_total'] = Supplier::query()->count();
    $hub['suppliers_active'] = Supplier::query()->where('is_active', true)->count();
    $hub['procurement_types_total'] = ProcurementType::query()->count();
    $hub['procurement_types_active'] = ProcurementType::query()->where('is_active', true)->count();

    $quickLinks = array_values(array_filter([
        auth()->user()->can('location.manage') ? ['label' => 'Kampus', 'keywords' => 'kampus campus lokasi', 'url' => route('admin.locations.campuses.index'), 'icon' => 'fa-university', 'count' => $hub['campuses_total'] ?? 0] : null,
        auth()->user()->can('location.manage') ? ['label' => 'Gedung', 'keywords' => 'gedung building', 'url' => route('admin.locations.campuses.index'), 'icon' => 'fa-building', 'count' => $hub['buildings_total'] ?? 0] : null,
        auth()->user()->can('location.manage') ? ['label' => 'Lantai', 'keywords' => 'lantai floor', 'url' => route('admin.locations.campuses.index'), 'icon' => 'fa-layer-group', 'count' => $hub['floors_total'] ?? 0] : null,
        auth()->user()->can('location.manage') ? ['label' => 'Ruangan', 'keywords' => 'ruangan room', 'url' => route('admin.locations.campuses.index'), 'icon' => 'fa-door-open', 'count' => $hub['rooms_total'] ?? 0] : null,
        auth()->user()->can('unit.manage') ? ['label' => 'Unit / Divisi', 'keywords' => 'unit divisi org', 'url' => route('categories.index', ['tab' => 'units']), 'icon' => 'fa-sitemap', 'count' => $hub['units_total']] : null,
        auth()->user()->can('category.manage') ? ['label' => 'Kategori', 'keywords' => 'kategori klasifikasi category', 'url' => route('categories.index', ['tab' => 'klasifikasi']), 'icon' => 'fa-tags', 'count' => $hub['categories_total']] : null,
        auth()->user()->can('category.manage') ? ['label' => 'Sub Kategori', 'keywords' => 'sub kategori subkategori', 'url' => route('categories.index', ['tab' => 'klasifikasi']), 'icon' => 'fa-tag', 'count' => $hub['sub_categories_total']] : null,
        auth()->user()->can('procurement.manage') ? ['label' => 'Jenis Pengadaan', 'keywords' => 'pengadaan procurement hibah leasing', 'url' => route('categories.index', ['tab' => 'pengadaan']), 'icon' => 'fa-file-invoice-dollar', 'count' => $hub['procurement_types_total']] : null,
        auth()->user()->can('vendor.manage') ? ['label' => 'Vendor', 'keywords' => 'vendor', 'url' => route('vendors.index'), 'icon' => 'fa-store', 'count' => $hub['vendors_total']] : null,
        auth()->user()->can('supplier.manage') ? ['label' => 'Supplier', 'keywords' => 'supplier', 'url' => route('suppliers.index'), 'icon' => 'fa-truck', 'count' => $hub['suppliers_total']] : null,
        ['label' => 'Status Inventaris', 'keywords' => 'status aktif nonaktif', 'url' => '#section-status-inventaris', 'icon' => 'fa-toggle-on', 'count' => count(InventoryStatus::cases())],
    ]));
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">Hub Master Data</h5>
        <p class="text-muted small mb-0">Pusat referensi sistem — quick access ke seluruh Master Data tanpa mengubah workflow existing.</p>
    </div>
</div>

<div class="card card-undira mb-4">
    <div class="card-body py-3">
        <label class="form-label small mb-1" for="hubMasterSearch">Cari Master Data</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="search" id="hubMasterSearch" class="form-control" placeholder="Contoh: Kampus, Vendor, Jenis Pengadaan…" autocomplete="off">
        </div>
        <p class="small text-muted mb-0 mt-2" id="hubMasterSearchHint">Ketik untuk memfilter kartu akses cepat di bawah.</p>
    </div>
</div>

<h6 class="text-uppercase text-muted small fw-semibold mb-2">Ringkasan &amp; Quick Access</h6>
<div class="row g-3 mb-4" id="hubMasterQuickAccess">
    @forelse($quickLinks as $link)
    <div class="col-md-6 col-lg-4 col-xl-3 hub-master-card"
         data-label="{{ strtolower($link['label']) }}"
         data-keywords="{{ strtolower($link['keywords']) }}">
        <div class="card card-undira h-100">
            <div class="card-body d-flex flex-column">
                <h6 class="card-title mb-1">
                    <i class="fas {{ $link['icon'] }} me-1 text-primary"></i>{{ $link['label'] }}
                </h6>
                <p class="small text-muted mb-3">{{ number_format($link['count']) }} data</p>
                <a href="{{ $link['url'] }}"
                   class="btn btn-sm btn-undira mt-auto{{ str_starts_with($link['url'], '#') ? '' : '' }}"
                   @if(str_starts_with($link['url'], '#')) data-no-loader="1" @endif>
                    Buka
                </a>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-light border mb-0">Tidak ada Master Data yang dapat diakses dengan permission Anda.</div>
    </div>
    @endforelse
    <div class="col-12 d-none" id="hubMasterEmptySearch">
        <div class="alert alert-warning border mb-0">Tidak ada Master Data yang cocok dengan pencarian.</div>
    </div>
</div>

{{-- Status Inventaris (referensi enum — bukan master tabel baru) --}}
<div class="card card-undira mb-4" id="section-status-inventaris">
    <div class="card-header">
        <strong><i class="fas fa-toggle-on me-1"></i>Status Inventaris</strong>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Status operasional inventaris dikelola sebagai referensi sistem (enum).
            Nilai utama: <strong>Aktif</strong> (masih digunakan) dan status terminal sebagai <strong>Nonaktif</strong> (hilang / dilelang / dimusnahkan).
            Field status pada form inventaris tetap memakai daftar ini — tidak diubah.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Label</th>
                        <th>Kelompok</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(InventoryStatus::cases() as $status)
                    <tr>
                        <td><code>{{ $status->value }}</code></td>
                        <td>{{ $status->label() }}</td>
                        <td>
                            @if($status === InventoryStatus::Aktif)
                                <span class="badge bg-success">Aktif</span>
                            @elseif($status->isTerminal())
                                <span class="badge bg-secondary">Nonaktif</span>
                            @else
                                <span class="badge bg-info text-dark">Operasional</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<h6 class="text-uppercase text-muted small fw-semibold mb-2">Master Pendukung (existing)</h6>
<p class="small text-muted mb-3">Fungsi ruang, tahun pembelian, dan kondisi fisik tetap dikelola di sini agar Konfigurasi Inventaris tidak terganggu.</p>

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
                <h6 class="card-title mb-1"><i class="fas fa-heartbeat me-1"></i>Kondisi Fisik</h6>
                <p class="small text-muted mb-2">{{ $hub['conditions_active'] ?? 0 }} aktif / {{ $hub['conditions_total'] ?? 0 }} total</p>
                <a href="#section-kondisi" class="btn btn-sm btn-undira" data-no-loader="1">Kelola Kondisi</a>
            </div>
        </div>
    </div>
    @else
    <div class="col-12"><div class="alert alert-light border small mb-0">Master pendukung (fungsi ruang, tahun, kondisi) memerlukan izin category.manage.</div></div>
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
    <strong>Catatan:</strong> Kondisi di sini adalah kondisi fisik aset (Baik, Layak, Rusak), berbeda dari Status Inventaris (operasional).
    Merk dan Tahun Pengadaan tetap diisi pada form inventaris — tidak dijadikan menu Master Data.
</div>
@endcan

@push('scripts')
<script>
(function () {
    const input = document.getElementById('hubMasterSearch');
    const cards = document.querySelectorAll('#hubMasterQuickAccess .hub-master-card');
    const empty = document.getElementById('hubMasterEmptySearch');
    if (!input) return;

    const filter = () => {
        const q = (input.value || '').trim().toLowerCase();
        let visible = 0;
        cards.forEach((card) => {
            const hay = (card.dataset.label || '') + ' ' + (card.dataset.keywords || '');
            const show = !q || hay.includes(q);
            card.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        if (empty) empty.classList.toggle('d-none', visible > 0 || cards.length === 0);
    };

    input.addEventListener('input', filter);

    // Scroll ke section status bila dibuka dari menu
    if (window.location.hash === '#section-status-inventaris' || new URLSearchParams(window.location.search).get('section') === 'status') {
        const el = document.getElementById('section-status-inventaris');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
})();
</script>
@endpush
