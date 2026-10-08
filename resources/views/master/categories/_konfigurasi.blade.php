{{-- Konfigurasi Inventaris (primary Kategori table) --}}
@php
    $lookups = $configLookups ?? [];
    $old = old();
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">Konfigurasi Inventaris</h5>
        <p class="text-muted small mb-0">Pusat input inventaris: lokasi → klasifikasi → data barang → QR Code otomatis tersimpan ke ruangan.</p>
    </div>
    @canany(['category.manage', 'inventory.create'])
    <button type="button" class="btn btn-undira btn-sm" id="btnOpenCreateConfig" data-bs-toggle="modal" data-bs-target="#modalCreateConfig" data-no-loader="1">
        <i class="fas fa-plus me-1"></i>Tambah Inventaris
    </button>
    @endcanany
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
                            @php
                                $relatedCount = (int) ($cfg->related_inventory_count ?? 0);
                                $blockedCount = (int) ($cfg->related_blocked_count ?? 0);
                                $configLabel = trim(($cfg->category?->name ?? 'Klasifikasi').' / '.($cfg->subCategory?->name ?? 'Subkategori'));
                                $deleteConfirm = $blockedCount > 0
                                    ? $configLabel.'. '.$relatedCount.' inventaris terkait, '.$blockedCount.' sudah memiliki riwayat transaksi. Penghapusan permanen akan ditolak agar history tidak rusak.'
                                    : $configLabel.'. '.$relatedCount.' inventaris terkait akan ikut dihapus permanen. Nomor inventaris yang sudah ada tidak berubah.';
                            @endphp
                            <form action="{{ route('master-configurations.destroy', $cfg) }}" method="POST" class="d-inline"
                                  data-confirm-title="Hapus Konfigurasi Inventaris?"
                                  data-confirm="{{ $deleteConfirm }}"
                                  data-confirm-button="Hapus Permanen"
                                  data-confirm-color="#dc3545">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus permanen"><i class="fas fa-trash"></i></button>
                            </form>
                            <div class="small text-muted">{{ $relatedCount }} inventaris terkait</div>
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

@canany(['category.manage', 'inventory.create'])
@push('modals')
{{-- CREATE --}}
<div class="modal fade" id="modalCreateConfig" tabindex="-1" aria-labelledby="modalCreateConfigLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <form action="{{ route('master-configurations.store') }}" method="POST" class="modal-content" id="formCreateConfig">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateConfigLabel">Tambah Inventaris via Konfigurasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="manageOptionPanel" class="manage-option-panel d-none mb-3" role="region" aria-label="Tambah pilihan master">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small text-uppercase text-muted mb-0">Tambah Pilihan Master</strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseManageOption" aria-label="Tutup panel pilihan">Tutup</button>
                    </div>
                    <input type="hidden" id="option_type">
                    <input type="hidden" id="option_target_select">
                    <div id="option_fields_code_name" class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small" for="option_code">Kode <span class="text-danger">*</span></label>
                            <input type="text" id="option_code" class="form-control form-control-sm" maxlength="30">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small" for="option_name">Nama <span class="text-danger">*</span></label>
                            <input type="text" id="option_name" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div id="option_fields_year" class="row g-2 d-none">
                        <div class="col-md-4">
                            <label class="form-label small" for="option_year">Tahun <span class="text-danger">*</span></label>
                            <input type="number" id="option_year" class="form-control form-control-sm" min="1990" max="2100">
                        </div>
                    </div>
                    <div class="alert alert-danger py-2 small d-none mt-2 mb-0" id="option_error" role="alert"></div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-undira" id="btnSaveManageOption">Simpan Pilihan</button>
                    </div>
                </div>
                @include('master.categories._config-form-fields', ['prefix' => 'create', 'lookups' => $lookups])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira" id="btnSaveCreateConfig"><i class="fas fa-save me-1"></i>Simpan Inventaris</button>
            </div>
        </form>
    </div>
</div>
@endpush

@include('master.categories._config-form-script')
@endcanany

@can('category.manage')
@push('modals')
{{-- EDIT --}}
<div class="modal fade" id="modalEditConfig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <form method="POST" class="modal-content" id="formEditConfig">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Konfigurasi Inventaris</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="manageOptionPanelEdit" class="manage-option-panel d-none mb-3" role="region" aria-label="Tambah pilihan master">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small text-uppercase text-muted mb-0">Tambah Pilihan Master</strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-close-manage-option" aria-label="Tutup panel pilihan">Tutup</button>
                    </div>
                    <p class="small text-muted mb-2 mb-0">Gunakan tombol + pada form untuk menambah pilihan, atau kelola master data di tab terkait.</p>
                </div>
                @include('master.categories._config-form-fields', ['prefix' => 'edit', 'lookups' => $lookups])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endpush
@endcan
