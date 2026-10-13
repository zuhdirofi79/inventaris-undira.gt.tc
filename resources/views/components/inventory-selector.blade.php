@props([
    'name' => 'inventory_item_id',
    'selectedId' => null,
    'selectedLabel' => null,
    'statusFilter' => null,
    'requireBorrowable' => false,
    'showQrScan' => false,
    'modes' => 'both', // location|quick|both
    'label' => 'Inventaris',
    'required' => true,
    'helpText' => null,
    'submitControl' => null,
    'resolveMaintenance' => false,
    'partialScope' => false,
    'scheduleStart' => null,
    'scheduleEnd' => null,
    'id' => null,
])

@php
    $uid = $id ?: 'invsel-'.uniqid();
    $modes = in_array($modes, ['location', 'quick', 'both'], true) ? $modes : 'both';
    $showLocation = $modes === 'location' || $modes === 'both';
    $showQuick = $modes === 'quick' || $modes === 'both';
    $defaultMode = $showLocation ? 'location' : 'quick';
    $selectedId = old($name, $selectedId);
    $urls = [
        'campuses' => route('inventory.selector.campuses'),
        'buildings' => route('inventory.selector.buildings'),
        'floors' => route('inventory.selector.floors'),
        'rooms' => route('inventory.selector.rooms'),
        'categories' => route('inventory.selector.categories'),
        'subCategories' => route('inventory.selector.sub-categories'),
        'items' => route('inventory.selector.items'),
        'search' => route('inventory.selector.search'),
        'show' => url('/inventory/selector/items'),
        'resolveCode' => route('inventory.selector.resolve-code'),
        'maintenanceContext' => url('/inventory/selector/items'),
    ];
@endphp

@once
@push('styles')
<style>
.invsel-mode-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
    margin-bottom: 1rem;
}
.invsel-mode-tabs .btn {
    border-width: 2px;
    font-weight: 600;
    text-align: left;
    padding: .75rem 1rem;
}
.invsel-mode-tabs .btn.active {
    background: var(--undira-green, #2E7D32);
    border-color: var(--undira-green, #2E7D32);
    color: #fff;
}
.invsel-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem 1rem;
}
@media (max-width: 767.98px) {
    .invsel-mode-tabs { grid-template-columns: 1fr; }
    .invsel-detail-grid { grid-template-columns: 1fr; }
}
.invsel-detail-grid .label {
    display: block;
    font-size: .75rem;
    font-weight: 600;
    color: var(--ui-muted, #64748b);
    margin-bottom: .15rem;
}
.invsel-detail-grid .value {
    font-weight: 600;
    color: var(--ui-text, #0f172a);
    word-break: break-word;
}
[data-theme="dark"] .invsel-detail-grid .value,
body.dark-mode .invsel-detail-grid .value {
    color: var(--dm-text, #e2e8f0);
}
.invsel-blocked { display: none; }
.invsel-blocked.is-visible { display: block; }
.invsel-qr-reader {
    min-height: 240px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: .5rem;
    overflow: hidden;
}
.select2-results__option { white-space: normal; }
.select2-results__option .invsel-opt-name { font-weight: 700; color: #0f172a; }
.select2-results__option .invsel-opt-num { font-weight: 700; color: #1e3a5f; word-break: break-all; }
.select2-results__option .invsel-opt-meta { font-size: .8rem; color: #64748b; }
.select2-results__option .invsel-opt-badge {
    display: inline-block;
    margin-top: .2rem;
    padding: .05rem .45rem;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #d97706;
}
.select2-results__option .invsel-opt-reason { font-size: .8rem; color: #92400e; }
.select2-container--bootstrap-5 .select2-results__option--disabled { cursor: not-allowed; }
.select2-container--bootstrap-5 .select2-results__option--disabled .invsel-opt { opacity: 1; }
</style>
@endpush
@endonce

<div
    class="inventory-selector"
    id="{{ $uid }}"
    data-inventory-selector
    data-name="{{ $name }}"
    data-status-filter="{{ $statusFilter ?? '' }}"
    data-require-borrowable="{{ $requireBorrowable ? '1' : '0' }}"
    data-show-qr="{{ $showQrScan ? '1' : '0' }}"
    data-modes="{{ $modes }}"
    data-default-mode="{{ $defaultMode }}"
    data-submit-control="{{ $submitControl ?? '' }}"
    data-resolve-maintenance="{{ $resolveMaintenance ? '1' : '0' }}"
    data-partial-scope="{{ $partialScope ? '1' : '0' }}"
    data-schedule-start="{{ $scheduleStart ?? '' }}"
    data-schedule-end="{{ $scheduleEnd ?? '' }}"
    data-urls='@json($urls)'
    data-csrf="{{ csrf_token() }}"
>
    @if($showLocation && $showQuick)
    <div class="invsel-mode-tabs" role="tablist">
        <button type="button" class="btn btn-outline-success active" data-invsel-tab="location" data-no-loader="1">
            <i class="fas fa-map-marker-alt me-2"></i>Cari Berdasarkan Lokasi
            <div class="small fw-normal mt-1 opacity-75">Kampus → Gedung → Lantai → Ruangan → Kategori → Sub → Barang</div>
        </button>
        <button type="button" class="btn btn-outline-primary" data-invsel-tab="quick" data-no-loader="1">
            <i class="fas fa-qrcode me-2"></i>Cari Nomor Inventaris / QR
            <div class="small fw-normal mt-1 opacity-75">Nomor · Nama · QR · Barcode · Serial</div>
        </button>
    </div>
    @endif

    @if($showLocation)
    <div data-invsel-panel="location" class="{{ $defaultMode === 'location' ? '' : 'd-none' }}">
        <p class="small text-muted mb-2">
            @if($partialScope)
                Pilih kampus untuk langsung mencari. Gedung, lantai, ruangan, kategori, dan subkategori mempersempit hasil.
            @else
                Pilih berurutan dari atas. Dropdown berikutnya aktif setelah pilihan sebelumnya diisi.
            @endif
        </p>
        <div class="row g-2">
            <div class="col-md-6 col-lg-4">
                <label class="form-label">1. Kampus</label>
                <select class="form-select" data-invsel-campus></select>
            </div>
            <div class="col-md-6 col-lg-4">
                <label class="form-label">2. Gedung</label>
                <select class="form-select" data-invsel-building disabled></select>
            </div>
            <div class="col-md-6 col-lg-4">
                <label class="form-label">3. Lantai</label>
                <select class="form-select" data-invsel-floor disabled></select>
            </div>
            <div class="col-md-6 col-lg-4">
                <label class="form-label">4. Ruangan</label>
                <select class="form-select" data-invsel-room disabled></select>
            </div>
            <div class="col-md-6 col-lg-4">
                <label class="form-label">5. Kategori</label>
                <select class="form-select" data-invsel-category disabled></select>
            </div>
            <div class="col-md-6 col-lg-4">
                <label class="form-label">6. Sub Kategori</label>
                <select class="form-select" data-invsel-subcategory disabled></select>
            </div>
            <div class="col-12">
                <label class="form-label">7. {{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                <select class="form-select" data-invsel-location-item data-placeholder="{{ $partialScope ? 'Ketik nomor, nama, barcode, atau serial…' : 'Pilih inventaris setelah filter lengkap…' }}"></select>
                <div class="form-text">
                    @if($statusFilter === 'aktif')
                        Hanya menampilkan barang berstatus Aktif (siap dipinjam).
                    @elseif($statusFilter)
                        Hanya inventaris status {{ ucfirst($statusFilter) }}.
                    @endif
                    @if($partialScope)
                        Cukup pilih kampus, lalu ketik kata kunci. Filter yang kosong tidak mematikan pencarian.
                    @else
                        Setelah Sub Kategori dipilih, daftar barang muncul. Ketik ≥2 karakter untuk menyaring.
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($showQuick)
    <div data-invsel-panel="quick" class="{{ $defaultMode === 'quick' ? '' : 'd-none' }}">
        <div class="row g-3">
            <div class="{{ $showQrScan ? 'col-lg-7' : 'col-12' }}">
                <label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
                <select class="form-select" data-invsel-quick-item data-placeholder="Ketik minimal 2 karakter…">
                    @if($selectedId && $selectedLabel)
                        <option value="{{ $selectedId }}" selected>{{ $selectedLabel }}</option>
                    @endif
                </select>
                @if($helpText)
                    <div class="form-text">{{ $helpText }}</div>
                @else
                    <div class="form-text">Cari nomor inventaris, nama, barcode, atau serial (min. 2 karakter).</div>
                @endif
            </div>
            @if($showQrScan)
            <div class="col-lg-5">
                <label class="form-label d-block">Scan QR / tempel kode</label>
                <button type="button" class="btn btn-outline-secondary w-100 mb-2" data-invsel-toggle-camera data-no-loader="1">
                    <i class="fas fa-camera me-1"></i><span data-invsel-camera-label>Mulai Kamera</span>
                </button>
                <div class="invsel-qr-reader d-none" data-invsel-qr-reader></div>
                <div class="input-group mt-2">
                    <input type="text" class="form-control" data-invsel-manual-code placeholder="Tempel nomor, barcode, serial, atau URL QR…" autocomplete="off">
                    <button type="button" class="btn btn-undira" data-invsel-resolve-code data-no-loader="1">Cari</button>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <input type="hidden"
           name="{{ $name }}"
           data-invsel-hidden
           value="{{ $selectedId }}"
           @if($required) required @endif>

    @if($requireBorrowable)
    <div class="alert alert-danger mt-3 mb-0 invsel-blocked" data-invsel-blocked role="alert">
        <div class="fw-bold mb-1"><i class="fas fa-ban me-1"></i><span data-invsel-blocked-title>Tidak dapat dipinjam</span></div>
        <div class="small mb-0" data-invsel-blocked-detail></div>
        <div class="small mt-1 mb-0">Pilih barang lain yang statusnya Aktif. Tombol Simpan dikunci.</div>
    </div>
    @endif

    <div class="border rounded p-3 mt-3 d-none" data-invsel-preview>
        <div class="fw-semibold mb-2 text-undira-blue"><i class="fas fa-info-circle me-1"></i>Pratinjau Inventaris</div>
        <div class="invsel-detail-grid">
            <div><span class="label">Nomor Inventaris</span><span class="value" data-field="inventory_number">—</span></div>
            <div><span class="label">Nama Barang</span><span class="value" data-field="name">—</span></div>
            <div><span class="label">Kategori</span><span class="value" data-field="category">—</span></div>
            <div><span class="label">Sub Kategori</span><span class="value" data-field="sub_category">—</span></div>
            <div><span class="label">Kampus</span><span class="value" data-field="campus">—</span></div>
            <div><span class="label">Gedung</span><span class="value" data-field="building">—</span></div>
            <div><span class="label">Lantai</span><span class="value" data-field="floor">—</span></div>
            <div><span class="label">Ruangan</span><span class="value" data-field="room">—</span></div>
            <div><span class="label">PIC</span><span class="value" data-field="pic">—</span></div>
            <div><span class="label">Kondisi</span><span class="value" data-field="condition_label">—</span></div>
            <div><span class="label">Status</span><span class="value" data-field="status_label">—</span></div>
            <div><span class="label">Barcode</span><span class="value" data-field="barcode">—</span></div>
            <div><span class="label">Serial</span><span class="value" data-field="serial_number">—</span></div>
        </div>
    </div>
</div>

@once
@push('styles')
@include('partials.select2-cdn-css')
@endpush
@push('scripts')
@include('partials.select2-cdn-js')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="{{ asset('js/inventory-selector.js') }}?v={{ @filemtime(public_path('js/inventory-selector.js')) ?: time() }}"></script>
@endpush
@endonce
