@extends('layouts.app')

@section('title', 'Semua Item Inventaris - Inventaris UNDIRA')

@push('styles')
@include('partials.datatables-cdn-css')
@include('partials.select2-cdn-css')
<style>
/* Scoped to Inventory Items — keep page width = viewport; table scrolls inside DT shell. */
.inventory-items-page {
    max-width: 100%;
    min-width: 0;
}
/* page-header/layout use class "min-w-0" but Tailwind is NOT loaded on app pages.
   Provide the intended CSS locally so flex children can shrink without page overflow. */
.inventory-items-page .min-w-0 {
    min-width: 0;
}
/* Containment only — do NOT set overflow-x here.
   DataTables scrollX already creates .dataTables_scrollBody as the single horizontal scroller. */
.inventory-items-page .inventory-items-table-shell {
    max-width: 100%;
    min-width: 0;
}
.inventory-items-page .dataTables_wrapper {
    max-width: 100%;
    min-width: 0;
}
.inventory-items-page .dataTables_scroll {
    max-width: 100%;
}
@media (max-width: 767.98px) {
    .inventory-items-page .filter-panel {
        padding: 0.85rem 0.9rem;
    }
    .inventory-items-page #btnApplyFilter,
    .inventory-items-page #btnResetFilter {
        min-height: 2.5rem;
        min-width: 2.5rem;
        padding-left: 0.9rem;
        padding-right: 0.9rem;
    }
    .inventory-items-page #inventoryTable .btn-xs,
    .inventory-items-page #inventoryTable .btn-sm {
        min-width: 2.5rem;
        min-height: 2.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.35rem !important;
    }
    .inventory-items-page .dataTables_wrapper .dataTables_length,
    .inventory-items-page .dataTables_wrapper .dataTables_filter,
    .inventory-items-page .dataTables_wrapper .dataTables_info,
    .inventory-items-page .dataTables_wrapper .dataTables_paginate {
        float: none !important;
        text-align: left !important;
        width: 100%;
        margin-bottom: 0.5rem;
    }
    .inventory-items-page .dataTables_wrapper .dataTables_filter input {
        width: 100% !important;
        margin-left: 0 !important;
        display: block;
        margin-top: 0.35rem;
        max-width: 100%;
        box-sizing: border-box;
    }
    .inventory-items-page .dataTables_wrapper .dataTables_paginate .paginate_button {
        min-height: 2.25rem;
        padding: 0.35rem 0.65rem !important;
    }
    .inventory-items-page #inventoryTable .inv-check-wrap {
        min-height: 2.5rem;
        min-width: 2.5rem;
    }
}
</style>
@endpush

@section('content')
<div class="inventory-items-page">
<x-page-header
    title="Data Inventaris"
    subtitle="Daftar flat dengan filter & DataTables"
    icon="bi bi-list-ul"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Inventaris', 'url' => route('inventory.index')],
        ['label' => 'Data Inventaris'],
    ]"
    :back-route="route('inventory.index')"
    back-label="Dashboard Inventaris"
>
    @can('inventory.create')
    <a href="{{ route('inventory.create') }}" class="btn btn-undira btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Tambah Inventaris
    </a>
    @endcan
    <button type="button" class="btn btn-outline-dark btn-sm" id="btnMassLabels" data-bs-toggle="modal" data-bs-target="#massLabelsModal">
        <i class="bi bi-qr-code me-1"></i>Cetak Label QR
    </button>
    @canany(['inventory.import', 'inventory.export'])
    <div class="btn-group btn-group-sm">
        <button type="button"
                class="btn btn-outline-secondary dropdown-toggle"
                data-bs-toggle="dropdown"
                data-bs-auto-close="true"
                data-bs-popper-config='{"strategy":"fixed"}'
                aria-expanded="false"
                data-no-loader="1">
            Aksi
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            @can('inventory.import')
            <li>
                <a class="dropdown-item" href="{{ route('inventory.import.index') }}">
                    <i class="bi bi-upload me-2"></i>Import Excel
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="{{ route('inventory.import.template') }}" data-no-loader="1">
                    <i class="bi bi-download me-2"></i>Download Template Excel
                </a>
            </li>
            @endcan
            @if(auth()->user()->can('inventory.import') && auth()->user()->can('inventory.export'))
            <li><hr class="dropdown-divider"></li>
            @endif
            @can('inventory.export')
            <li>
                <a class="dropdown-item" href="{{ route('inventory.export.excel') }}" data-no-loader="1">
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="{{ route('inventory.export.pdf') }}" data-no-loader="1">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="{{ route('inventory.export.csv') }}" data-no-loader="1">
                    <i class="bi bi-filetype-csv me-2"></i>Export CSV
                </a>
            </li>
            @endcan
        </ul>
    </div>
    @endcanany
    <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-building me-1"></i>Per Kampus</a>
    <a href="{{ route('inventory.categories') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-grid-3x3-gap me-1"></i>Per Kategori</a>
</x-page-header>

<div class="filter-panel" id="mass-labels">
    <form id="filterForm">
        <div class="row g-2 g-md-3">
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small">Kampus</label>
                <select id="filterCampus" class="form-select select2-filter">
                    <option value="">Semua Kampus</option>
                    @foreach($locationData as $campus)
                        <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small">Gedung</label>
                <select id="filterBuilding" class="form-select select2-filter"><option value="">Semua Gedung</option></select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small">Lantai</label>
                <select id="filterFloor" class="form-select select2-filter"><option value="">Semua Lantai</option></select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small">Ruangan</label>
                <select id="filterRoom" class="form-select select2-filter"><option value="">Semua Ruangan</option></select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small">Kategori</label>
                <select id="filterCategory" class="form-select select2-filter">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small">Unit Kerja</label>
                <select id="filterOrgUnit" class="form-select select2-filter">
                    <option value="">Semua Unit Kerja</option>
                    @foreach($orgUnits ?? [] as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small">PIC</label>
                <select id="filterPic" class="form-select select2-filter">
                    <option value="">Semua PIC</option>
                    @foreach($users ?? [] as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small">Status</label>
                <select id="filterStatus" class="form-select">
                    <option value="">Semua</option>
                    @foreach(\App\Enums\InventoryStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small">Kondisi</label>
                <select id="filterCondition" class="form-select">
                    <option value="">Semua</option>
                    @foreach(\App\Support\InventoryConditionCatalog::filterOptions() as $cond)
                        <option value="{{ $cond->value }}">{{ $cond->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                <button type="button" id="btnApplyFilter" class="btn btn-undira btn-sm"><i class="fas fa-filter me-1"></i>Terapkan</button>
                <button type="button" id="btnResetFilter" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo me-1"></i>Reset</button>
                <span class="small text-muted ms-md-2" id="massSelectInfo">0 item dipilih untuk label</span>
            </div>
        </div>
    </form>
</div>

<div class="card card-undira">
    <div class="card-body">
        {{-- Containment wrapper only. Horizontal scroll = DataTables scrollX (single layer). --}}
        <div class="inventory-items-table-shell">
            <table id="inventoryTable" class="table table-striped table-hover w-100 inventory-items-table">
                <thead>
                    <tr>
                        <th class="inv-check-col sorting_disabled" scope="col">
                            <span class="inv-check-wrap d-flex justify-content-center align-items-center">
                                <input type="checkbox" id="checkPageAll" class="form-check-input inv-check" title="Pilih Semua Inventaris" data-no-loader="1" aria-label="Pilih Semua Inventaris">
                            </span>
                        </th>
                        <th>No. Inventaris</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>PIC</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

{{--
  Mass label PDF download: native POST (no iframe, no fetch/blob).
  Backend returns Content-Disposition:attachment → browser downloads and keeps this page.
--}}
<form id="massLabelForm"
      method="POST"
      action="{{ route('inventory.labels.mass30') }}"
      class="d-none"
      data-download
      data-no-loading
      data-no-loader="1">
    @csrf
    <input type="hidden" name="scope" id="massLabelScope" value="selected">
    <div id="massLabelIds"></div>
    <input type="hidden" name="campus_id" id="massFilterCampus">
    <input type="hidden" name="building_id" id="massFilterBuilding">
    <input type="hidden" name="floor_id" id="massFilterFloor">
    <input type="hidden" name="room_id" id="massFilterRoom">
    <input type="hidden" name="category_id" id="massFilterCategory">
    <input type="hidden" name="org_unit_id" id="massFilterOrgUnit">
    <input type="hidden" name="pic_id" id="massFilterPic">
    <input type="hidden" name="status" id="massFilterStatus">
    <input type="hidden" name="condition" id="massFilterCondition">
</form>
</div>{{-- /.inventory-items-page --}}
@endsection

{{--
  CRITICAL: modal MUST be outside .content-wrapper (AdminLTE stacking context).
  Backdrop is appended to <body>; a modal trapped in content sits UNDER the backdrop.
  See public/css/undira-modals.css and layouts/app.blade.php @stack('modals').
--}}
@push('modals')
<div class="modal fade" id="massLabelsModal" tabindex="-1" aria-labelledby="massLabelsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="massLabelsModalLabel">Print Barcode (45 Label / A4)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Generate PDF A4 Portrait — grid <strong>5 kolom × 9 baris</strong> (45 label/halaman).</p>
                <p class="small text-muted mb-3">Fitur ini terpisah dari Cetak Label individual. QR tetap fokus utama pada setiap label.</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-undira" id="btnPrintSelectedLabels" data-no-loader="1">
                        Cetak yang dipilih (<span id="massSelectedCount">0</span>)
                    </button>
                    <button type="button" class="btn btn-outline-dark" id="btnPrintFilterLabels" data-no-loader="1">
                        Cetak semua hasil filter
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
@include('partials.datatables-cdn-js')
@include('partials.select2-cdn-js')
<script>
const locationData = @json($locationData);
const selectedIds = new Set();
@include('partials.datatables-id-language')
$('.select2-filter').select2({ theme: 'bootstrap-5', width: '100%' });

function populateSelect($select, items, valueKey, labelKey, placeholder) {
    $select.empty().append(`<option value="">${placeholder}</option>`);
    items.forEach(item => $select.append(`<option value="${item[valueKey]}">${item[labelKey]}</option>`));
    $select.trigger('change');
}

function updateMassSelectInfo() {
    const n = selectedIds.size;
    $('#massSelectInfo').text(n + ' item dipilih untuk label');
    $('#massSelectedCount').text(n);
}

/** Keep header checkbox + row highlight aligned with current page selection (UI only). */
function syncInventoryCheckboxUi() {
    const pageIds = [];
    $('#inventoryTable tbody tr').each(function () {
        const $row = $(this);
        const $cb = $row.find('.row-check');
        if (!$cb.length) {
            $row.removeClass('inv-row-selected');
            return;
        }
        const id = String($cb.val());
        pageIds.push(id);
        const checked = selectedIds.has(id);
        $cb.prop('checked', checked);
        $row.toggleClass('inv-row-selected', checked);
    });

    const $all = $('#checkPageAll');
    if (!$all.length) return;
    const allChecked = pageIds.length > 0 && pageIds.every(function (id) { return selectedIds.has(id); });
    const someChecked = pageIds.some(function (id) { return selectedIds.has(id); });
    $all.prop('checked', allChecked);
    $all.prop('indeterminate', someChecked && !allChecked);
}

$('#filterCampus').on('change', function() {
    const campus = locationData.find(c => c.id == $(this).val());
    populateSelect($('#filterBuilding'), campus?.buildings || [], 'id', 'name', 'Semua Gedung');
    populateSelect($('#filterFloor'), [], 'id', 'name', 'Semua Lantai');
    populateSelect($('#filterRoom'), [], 'id', 'name', 'Semua Ruangan');
});
$('#filterBuilding').on('change', function() {
    const campus = locationData.find(c => c.id == $('#filterCampus').val());
    const building = campus?.buildings?.find(b => b.id == $(this).val());
    populateSelect($('#filterFloor'), building?.floors || [], 'id', 'name', 'Semua Lantai');
    populateSelect($('#filterRoom'), [], 'id', 'name', 'Semua Ruangan');
});
$('#filterFloor').on('change', function() {
    const campus = locationData.find(c => c.id == $('#filterCampus').val());
    const building = campus?.buildings?.find(b => b.id == $('#filterBuilding').val());
    const floor = building?.floors?.find(f => f.id == $(this).val());
    populateSelect($('#filterRoom'), floor?.rooms || [], 'id', 'name', 'Semua Ruangan');
});

function buildInventoryDataTableConfig(language) {
    const config = {
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: '{{ route('inventory.items') }}',
            data: function(d) {
                d.campus_id = $('#filterCampus').val();
                d.building_id = $('#filterBuilding').val();
                d.floor_id = $('#filterFloor').val();
                d.room_id = $('#filterRoom').val();
                d.category_id = $('#filterCategory').val();
                d.org_unit_id = $('#filterOrgUnit').val();
                d.pic_id = $('#filterPic').val();
                d.status = $('#filterStatus').val();
                d.condition = $('#filterCondition').val();
            },
            error: function (xhr) {
                let message = 'Gagal memuat data inventaris. Silakan hubungi Administrator.';
                try {
                    const payload = xhr.responseJSON;
                    if (payload && payload.error) {
                        message = payload.error;
                    }
                } catch (e) {}
                if (window.toastr) {
                    toastr.error(message);
                } else {
                    alert(message);
                }
            }
        },
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                width: '52px',
                className: 'inv-check-col dt-center text-center',
                render: function (id) {
                    const checked = selectedIds.has(String(id)) ? 'checked' : '';
                    return `<span class="inv-check-wrap d-flex justify-content-center align-items-center"><input type="checkbox" class="form-check-input inv-check row-check" value="${id}" ${checked} data-no-loader="1" aria-label="Pilih Inventaris"></span>`;
                }
            },
            { data: 'inventory_number', render: (data, type, row) => `<a href="/inventory/${row.id}">${data}</a>` },
            { data: 'name' },
            { data: 'category', render: c => c?.name || '-' },
            { data: 'pic_name', orderable: false, render: name => name
                ? `<span>${name}</span>`
                : `<span class="badge bg-secondary">Belum Ada PIC</span>` },
            { data: 'full_location', render: d => `<small>${d || '-'}</small>` },
            { data: 'status', render: s => { const label = (typeof s === 'object' && s?.label) ? s.label : (s || '-'); return `<span class="badge bg-secondary">${label}</span>`; }},
            { data: 'condition', render: c => { const label = (typeof c === 'object' && c?.label) ? c.label : (c || '-'); return `<span class="badge bg-info">${label}</span>`; }},
            { data: null, orderable: false, render: (data, type, row) => `
                <a href="/inventory/${row.id}" class="btn btn-xs btn-outline-primary btn-sm"><i class="fas fa-eye"></i></a>
                @can('inventory.update')<a href="/inventory/${row.id}/edit" class="btn btn-xs btn-outline-warning btn-sm"><i class="fas fa-edit"></i></a>@endcan
            `}
        ],
        columnDefs: [
            { targets: 0, width: '52px', className: 'inv-check-col dt-center text-center', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        drawCallback: function () {
            syncInventoryCheckboxUi();
            updateMassSelectInfo();
        }
    };

    if (language) {
        config.language = language;
    }

    return config;
}

// Local i18n object (no CDN language.url → no CORS). Fallback init without language if needed.
let table = null;
try {
    table = $('#inventoryTable').DataTable(buildInventoryDataTableConfig(datatablesIdLanguage));
} catch (dtErr) {
    console.error('DataTables init dengan i18n gagal, mencoba tanpa language pack:', dtErr);
    try {
        table = $('#inventoryTable').DataTable(buildInventoryDataTableConfig(null));
    } catch (dtErr2) {
        console.error('DataTables init gagal total (fitur lain tetap dijalankan):', dtErr2);
    }
}

$('#inventoryTable').on('change', '.row-check', function () {
    const id = String(this.value);
    if (this.checked) selectedIds.add(id);
    else selectedIds.delete(id);
    syncInventoryCheckboxUi();
    updateMassSelectInfo();
});

$('#checkPageAll').on('change', function () {
    const checked = this.checked;
    this.indeterminate = false;
    $('#inventoryTable tbody .row-check').each(function () {
        this.checked = checked;
        const id = String(this.value);
        if (checked) selectedIds.add(id);
        else selectedIds.delete(id);
    });
    syncInventoryCheckboxUi();
    updateMassSelectInfo();
});

function syncMassFilterFields() {
    $('#massFilterCampus').val($('#filterCampus').val() || '');
    $('#massFilterBuilding').val($('#filterBuilding').val() || '');
    $('#massFilterFloor').val($('#filterFloor').val() || '');
    $('#massFilterRoom').val($('#filterRoom').val() || '');
    $('#massFilterCategory').val($('#filterCategory').val() || '');
    $('#massFilterOrgUnit').val($('#filterOrgUnit').val() || '');
    $('#massFilterPic').val($('#filterPic').val() || '');
    $('#massFilterStatus').val($('#filterStatus').val() || '');
    $('#massFilterCondition').val($('#filterCondition').val() || '');
}

// Mass label download — set window.MASS_LABEL_DEBUG = true for diagnostics.
window.MASS_LABEL_DEBUG = window.MASS_LABEL_DEBUG === true;
function massLabelLog() {
    if (!window.MASS_LABEL_DEBUG) return;
    var args = Array.prototype.slice.call(arguments);
    args.unshift('[MassLabel]');
    console.log.apply(console, args);
}

const massLabelState = {
    submitting: false,
    clickedButton: null,
};

function setMassLabelButtonBusy(button, busy) {
    if (!button) return;
    button.disabled = !!busy;
    button.setAttribute('aria-busy', busy ? 'true' : 'false');
}

/** Remove orphaned Bootstrap chrome ONLY when no modal is visible. */
function cleanupOrphanedModalState() {
    if (document.querySelector('.modal.show')) {
        return;
    }
    document.querySelectorAll('.modal-backdrop').forEach(function (node) {
        if (node.parentNode) node.parentNode.removeChild(node);
    });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
}

function getMassLabelsModalElement() {
    return document.getElementById('massLabelsModal');
}

function getMassLabelsModalInstance() {
    const modalEl = getMassLabelsModalElement();
    if (!modalEl || !window.bootstrap || !bootstrap.Modal) return null;
    return bootstrap.Modal.getOrCreateInstance(modalEl, {
        backdrop: true,
        keyboard: true,
        focus: true,
    });
}

/**
 * Close modal and wait for Bootstrap to finish (backdrop removed), then resolve.
 * Does NOT forcibly strip backdrop while .modal.show exists.
 */
function closeMassLabelModalSafely() {
    return new Promise(function (resolve) {
        const modalEl = getMassLabelsModalElement();
        if (!modalEl) {
            cleanupOrphanedModalState();
            resolve();
            return;
        }

        if (!modalEl.classList.contains('show')) {
            cleanupOrphanedModalState();
            resolve();
            return;
        }

        let settled = false;
        function finish() {
            if (settled) return;
            settled = true;
            modalEl.removeEventListener('hidden.bs.modal', onHidden);
            cleanupOrphanedModalState();
            resolve();
        }

        function onHidden() {
            massLabelLog('hidden.bs.modal fired');
            finish();
        }

        modalEl.addEventListener('hidden.bs.modal', onHidden);
        try {
            const inst = getMassLabelsModalInstance();
            if (inst) inst.hide();
            else finish();
        } catch (err) {
            console.error('[MassLabel] modal hide failed', err);
            finish();
        }

        // Safety if hidden.bs.modal never fires (broken instance) — still do not
        // strip backdrop while another modal could be open (cleanup checks .modal.show).
        window.setTimeout(finish, 800);
    });
}

function massLabelPayloadSummary(form) {
    const formData = new FormData(form);
    return {
        scope: formData.get('scope'),
        inventory_ids_count: formData.getAll('inventory_ids[]').length,
        campus_id: formData.get('campus_id') || null,
        building_id: formData.get('building_id') || null,
        floor_id: formData.get('floor_id') || null,
        room_id: formData.get('room_id') || null,
        category_id: formData.get('category_id') || null,
        org_unit_id: formData.get('org_unit_id') || null,
        pic_id: formData.get('pic_id') || null,
        status: formData.get('status') || null,
        condition: formData.get('condition') || null,
    };
}

function downloadMassLabelPdf(form) {
    if (massLabelState.submitting) {
        massLabelLog('Ignored duplicate submit');
        return;
    }

    massLabelState.submitting = true;
    setMassLabelButtonBusy(massLabelState.clickedButton, true);
    massLabelLog('Close modal safely, then native form POST', massLabelPayloadSummary(form));

    closeMassLabelModalSafely()
        .then(function () {
            form.removeAttribute('target');
            HTMLFormElement.prototype.submit.call(form);
            massLabelLog('form.submit() invoked');
        })
        .catch(function (err) {
            console.error('[MassLabel]', err);
            const msg = (err && err.message) ? err.message : 'Label QR massal gagal diunduh.';
            if (window.toastr) toastr.error(msg);
            else alert(msg);
        })
        .finally(function () {
            massLabelState.submitting = false;
            setMassLabelButtonBusy(massLabelState.clickedButton, false);
            massLabelState.clickedButton = null;
            cleanupOrphanedModalState();
            massLabelLog('UI state restored');
        });
}

window.addEventListener('pageshow', function () {
    cleanupOrphanedModalState();
});

function submitMassLabels(scope) {
    syncMassFilterFields();
    $('#massLabelScope').val(scope);
    const $ids = $('#massLabelIds').empty();
    if (scope === 'selected') {
        if (selectedIds.size === 0) {
            const msg = 'Tidak ada inventaris yang dipilih. Centang item di tabel terlebih dahulu.';
            if (window.toastr) toastr.warning(msg);
            else alert(msg);
            return;
        }
        selectedIds.forEach(function (id) {
            $ids.append('<input type="hidden" name="inventory_ids[]" value="' + id + '">');
        });
    }

    const form = document.getElementById('massLabelForm');
    if (!form) {
        alert('Form cetak label tidak ditemukan.');
        return;
    }

    downloadMassLabelPdf(form);
}

$('#btnPrintSelectedLabels').on('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    console.count('[Mass Labels] print click');
    massLabelState.clickedButton = this;
    submitMassLabels('selected');
});
$('#btnPrintFilterLabels').on('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    console.count('[Mass Labels] print click');
    massLabelState.clickedButton = this;
    submitMassLabels('filter');
});

// One-time diagnostic helper (optional): window.diagnoseMassLabelModal()
window.diagnoseMassLabelModal = function () {
    const modal = getMassLabelsModalElement();
    const dialog = modal ? modal.querySelector('.modal-dialog') : null;
    const backdrop = document.querySelector('.modal-backdrop');
    console.table({
        modalCount: document.querySelectorAll('#massLabelsModal').length,
        modalParent: modal && modal.parentElement ? modal.parentElement.tagName : null,
        modalParentId: modal && modal.parentElement ? modal.parentElement.id : null,
        modalParentClass: modal && modal.parentElement ? modal.parentElement.className : null,
        underBody: !!(modal && modal.parentElement === document.body),
        modalZIndex: modal ? getComputedStyle(modal).zIndex : null,
        dialogZIndex: dialog ? getComputedStyle(dialog).zIndex : null,
        backdropZIndex: backdrop ? getComputedStyle(backdrop).zIndex : null,
        backdropCount: document.querySelectorAll('.modal-backdrop').length,
        bodyClass: document.body.className,
    });
    if (dialog) {
        const rect = dialog.getBoundingClientRect();
        const x = rect.left + rect.width / 2;
        const y = rect.top + rect.height / 2;
        console.log('elementFromPoint', document.elementFromPoint(x, y));
        console.log('elementsFromPoint', document.elementsFromPoint(x, y));
    }
    let current = modal;
    while (current) {
        const style = getComputedStyle(current);
        if (
            style.transform !== 'none' ||
            style.filter !== 'none' ||
            (style.backdropFilter && style.backdropFilter !== 'none') ||
            style.isolation === 'isolate' ||
            (style.contain && style.contain !== 'none') ||
            (style.opacity !== '1' && style.opacity !== '') ||
            (style.position !== 'static' && style.zIndex !== 'auto')
        ) {
            console.log('stacking-context candidate', {
                tag: current.tagName,
                id: current.id,
                className: current.className,
                position: style.position,
                zIndex: style.zIndex,
                transform: style.transform,
                filter: style.filter,
                opacity: style.opacity,
                isolation: style.isolation,
                contain: style.contain,
            });
        }
        current = current.parentElement;
    }
};

$('#btnApplyFilter').on('click', function () {
    if (table) table.ajax.reload();
});
$('#btnResetFilter').on('click', function() {
    $('#filterForm')[0].reset();
    $('.select2-filter').val('').trigger('change');
    selectedIds.clear();
    syncInventoryCheckboxUi();
    updateMassSelectInfo();
    if (table) table.ajax.reload();
});

if (window.location.hash === '#mass-labels') {
    setTimeout(function () {
        const btn = document.getElementById('btnMassLabels');
        if (btn) btn.click();
    }, 400);
}
</script>
@endpush
