{{-- Konfigurasi Inventaris (primary Kategori table) --}}
@php
    $lookups = $configLookups ?? [];
    $old = old();
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">Konfigurasi Inventaris</h5>
        <p class="text-muted small mb-0">Pusat konfigurasi master inventaris. Nomor urut hanya di-preview (tidak dikonsumsi di halaman ini).</p>
    </div>
    @can('category.manage')
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateConfig">
        <i class="fas fa-plus me-1"></i>Tambah
    </button>
    @endcan
</div>

<form method="GET" action="{{ route('categories.index') }}" class="card card-undira mb-3">
    <div class="card-body py-2">
        <input type="hidden" name="tab" value="konfigurasi">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-0">Cari</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Kode lokasi / ruangan…">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Kampus</label>
                <select name="campus_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach(($lookups['campuses'] ?? []) as $c)
                        <option value="{{ $c->id }}" @selected(request('campus_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Klasifikasi</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach(($lookups['categories'] ?? []) as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Status</label>
                <select name="is_active" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="1" @selected(request('is_active') === '1')>Aktif</option>
                    <option value="0" @selected(request('is_active') === '0')>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="{{ route('categories.index', ['tab' => 'konfigurasi']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive table-config-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Kampus</th>
                        <th>Gedung</th>
                        <th>Lantai</th>
                        <th>Unit / Divisi</th>
                        <th>Fungsi Ruang</th>
                        <th>Kode Ruangan</th>
                        <th>Jenis Pengadaan</th>
                        <th>Tahun</th>
                        <th>Klasifikasi</th>
                        <th>Subklasifikasi</th>
                        <th>Kondisi</th>
                        <th>No. Urut Terakhir</th>
                        <th>No. Urut Berikutnya</th>
                        <th>Kode Lokasi Otomatis</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($configurations as $cfg)
                    <tr>
                        <td>{{ $cfg->campus?->name }} <small class="text-muted">({{ $cfg->campus?->code }})</small></td>
                        <td>{{ $cfg->building?->name }}</td>
                        <td>{{ $cfg->floor?->name }}</td>
                        <td><code>{{ $cfg->orgUnit?->code }}</code></td>
                        <td>{{ $cfg->roomFunction?->name ?? '—' }}</td>
                        <td><code>{{ $cfg->room?->code }}</code></td>
                        <td>{{ $cfg->procurementType?->name }}</td>
                        <td>{{ $cfg->purchaseYear?->year }}</td>
                        <td>{{ $cfg->category?->name }}</td>
                        <td>{{ $cfg->subCategory?->name }}</td>
                        <td>{{ $cfg->inventoryCondition?->name }}</td>
                        <td><code>{{ $cfg->sequence_last_padded }}</code></td>
                        <td><code>{{ $cfg->sequence_next_padded }}</code></td>
                        <td><code class="text-primary">{{ $cfg->generated_location_code }}</code></td>
                        <td><span class="badge {{ $cfg->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $cfg->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="text-nowrap">
                            @can('category.manage')
                            @php
                                $editPayload = [
                                    'id' => $cfg->id,
                                    'campus_id' => $cfg->campus_id,
                                    'building_id' => $cfg->building_id,
                                    'floor_id' => $cfg->floor_id,
                                    'org_unit_id' => $cfg->org_unit_id,
                                    'room_function_id' => $cfg->room_function_id,
                                    'room_id' => $cfg->room_id,
                                    'procurement_type_id' => $cfg->procurement_type_id,
                                    'purchase_year_id' => $cfg->purchase_year_id,
                                    'category_id' => $cfg->category_id,
                                    'sub_category_id' => $cfg->sub_category_id,
                                    'inventory_condition_id' => $cfg->inventory_condition_id,
                                    'is_active' => $cfg->is_active,
                                    'generated_location_code' => $cfg->generated_location_code,
                                    'update_url' => route('master-configurations.update', $cfg),
                                ];
                            @endphp
                            <button type="button" class="btn btn-sm btn-outline-warning btn-edit-config"
                                data-bs-toggle="modal" data-bs-target="#modalEditConfig"
                                data-config="{{ e(json_encode($editPayload)) }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('master-configurations.destroy', $cfg) }}" method="POST" class="d-inline" data-confirm="Nonaktifkan konfigurasi ini?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="16" class="text-center text-muted py-5">
                            Belum ada konfigurasi inventaris. Klik <strong>Tambah</strong> untuk membuat kombinasi master.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($configurations->hasPages())
    <div class="card-footer">{{ $configurations->appends(request()->query())->links() }}</div>
    @endif
</div>

<style>
.table-config-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%; }
.table-config-scroll table { min-width: 1400px; }
</style>

@can('category.manage')
{{-- CREATE --}}
<div class="modal fade" id="modalCreateConfig" tabindex="-1" aria-labelledby="modalCreateConfigLabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form action="{{ route('master-configurations.store') }}" method="POST" class="modal-content" id="formCreateConfig">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateConfigLabel">Tambah Konfigurasi Inventaris</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                @include('master.categories._config-form-fields', ['prefix' => 'create', 'lookups' => $lookups])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan Konfigurasi</button>
            </div>
        </form>
    </div>
</div>

{{-- EDIT --}}
<div class="modal fade" id="modalEditConfig" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" class="modal-content" id="formEditConfig">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Konfigurasi Inventaris</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                @include('master.categories._config-form-fields', ['prefix' => 'edit', 'lookups' => $lookups])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>

{{-- Nested option manager --}}
<div class="modal fade" id="modalManageOption" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog">
        <form class="modal-content" id="formManageOption">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Pilihan Master</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="type" id="option_type">
                <input type="hidden" name="target_select" id="option_target_select">
                <div id="option_fields_code_name">
                    <div class="mb-3">
                        <label class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="option_code" class="form-control" maxlength="30">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="option_name" class="form-control">
                    </div>
                </div>
                <div id="option_fields_year" class="d-none">
                    <div class="mb-3">
                        <label class="form-label">Tahun <span class="text-danger">*</span></label>
                        <input type="number" name="year" id="option_year" class="form-control" min="1990" max="2100">
                    </div>
                </div>
                <div class="alert alert-danger d-none" id="option_error"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-undira">Simpan Pilihan</button>
            </div>
        </form>
    </div>
</div>

@include('master.categories._config-form-script')
@endcan
