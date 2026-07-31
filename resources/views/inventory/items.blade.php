@extends('layouts.app')

@section('title', 'Semua Item Inventaris - Inventaris UNDIRA')

@section('content')
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
            <div class="col-6 col-md-3">
                <label class="form-label small">Kampus</label>
                <select id="filterCampus" class="form-select select2-filter">
                    <option value="">Semua Kampus</option>
                    @foreach($locationData as $campus)
                        <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Gedung</label>
                <select id="filterBuilding" class="form-select select2-filter"><option value="">Semua Gedung</option></select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Lantai</label>
                <select id="filterFloor" class="form-select select2-filter"><option value="">Semua Lantai</option></select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Ruangan</label>
                <select id="filterRoom" class="form-select select2-filter"><option value="">Semua Ruangan</option></select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Kategori</label>
                <select id="filterCategory" class="form-select select2-filter">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Unit Kerja</label>
                <select id="filterOrgUnit" class="form-select select2-filter">
                    <option value="">Semua Unit Kerja</option>
                    @foreach($orgUnits ?? [] as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">PIC</label>
                <select id="filterPic" class="form-select select2-filter">
                    <option value="">Semua PIC</option>
                    @foreach($users ?? [] as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Status</label>
                <select id="filterStatus" class="form-select">
                    <option value="">Semua</option>
                    @foreach(\App\Enums\InventoryStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Kondisi</label>
                <select id="filterCondition" class="form-select">
                    <option value="">Semua</option>
                    @foreach(\App\Enums\InventoryCondition::cases() as $cond)
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
        <div class="table-responsive">
            <table id="inventoryTable" class="table table-striped table-hover w-100">
                <thead>
                    <tr>
                        <th style="width:36px;">
                            <input type="checkbox" id="checkPageAll" class="form-check-input" title="Pilih semua di halaman ini" data-no-loader="1">
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

{{-- Hidden iframe: browser handles Content-Disposition:attachment natively (no fetch/blob). --}}
<iframe id="massLabelDownloadFrame" name="massLabelDownloadFrame" title="Unduh Label PDF" class="d-none" style="width:0;height:0;border:0;position:absolute;"></iframe>

{{-- Mass label PDF: native POST into iframe — Laravel already returns a downloadable PDF. --}}
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

<div class="modal fade" id="massLabelsModal" tabindex="-1" aria-labelledby="massLabelsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="massLabelsModalLabel">Print Barcode (30 Label / A4)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Generate PDF A4 Portrait — grid <strong>3 kolom × 10 baris</strong> (30 label/halaman).</p>
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
@endsection

@push('scripts')
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
                className: 'text-center',
                render: function (id) {
                    const checked = selectedIds.has(String(id)) ? 'checked' : '';
                    return `<input type="checkbox" class="form-check-input row-check" value="${id}" ${checked} data-no-loader="1">`;
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
        order: [[1, 'asc']],
        drawCallback: function () {
            const pageIds = [];
            $('#inventoryTable tbody .row-check').each(function () {
                pageIds.push(String(this.value));
                this.checked = selectedIds.has(String(this.value));
            });
            const allChecked = pageIds.length > 0 && pageIds.every(id => selectedIds.has(id));
            $('#checkPageAll').prop('checked', allChecked);
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
    updateMassSelectInfo();
});

$('#checkPageAll').on('change', function () {
    const checked = this.checked;
    $('#inventoryTable tbody .row-check').each(function () {
        this.checked = checked;
        const id = String(this.value);
        if (checked) selectedIds.add(id);
        else selectedIds.delete(id);
    });
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

// Debug logs for Mass Label — set window.MASS_LABEL_DEBUG = false to silence.
window.MASS_LABEL_DEBUG = (typeof window.MASS_LABEL_DEBUG === 'undefined') ? true : window.MASS_LABEL_DEBUG;
function massLabelLog() {
    if (!window.MASS_LABEL_DEBUG) return;
    var args = Array.prototype.slice.call(arguments);
    args.unshift('[MassLabel]');
    console.log.apply(console, args);
}

let massLabelLoadingTimer = null;
let massLabelSafetyTimer = null;
let massLabelPostSubmitTimer = null;
let massLabelLoadingSettled = true;

function clearMassLabelTimers() {
    if (massLabelLoadingTimer) {
        window.clearTimeout(massLabelLoadingTimer);
        massLabelLoadingTimer = null;
    }
    if (massLabelSafetyTimer) {
        window.clearTimeout(massLabelSafetyTimer);
        massLabelSafetyTimer = null;
    }
    if (massLabelPostSubmitTimer) {
        window.clearTimeout(massLabelPostSubmitTimer);
        massLabelPostSubmitTimer = null;
    }
}

function stopMassLabelLoading(reason) {
    clearMassLabelTimers();
    if (typeof hideLoading === 'function') {
        hideLoading();
    }
    if (!massLabelLoadingSettled) {
        massLabelLoadingSettled = true;
        massLabelLog('Loading closed:', reason || 'stop');
    }
}

function startMassLabelLoading() {
    clearMassLabelTimers();
    massLabelLoadingSettled = false;
    // Wait for Bootstrap modal close — undira-loading skips overlay while .modal.show exists.
    massLabelLoadingTimer = window.setTimeout(function () {
        massLabelLoadingTimer = null;
        if (massLabelLoadingSettled) return;
        if (typeof showLoading === 'function') {
            showLoading('Menyiapkan label PDF...', 'Mengunduh berkas...');
            massLabelLog('Loading shown');
        }
    }, 350);
    // Hard safety: never leave overlay stuck (PDF gen can outlive overlay; download still proceeds).
    massLabelSafetyTimer = window.setTimeout(function () {
        massLabelSafetyTimer = null;
        stopMassLabelLoading('safety-timeout');
    }, 15000);
}

function closeMassLabelsModal() {
    const modalEl = document.getElementById('massLabelsModal');
    if (!modalEl || !window.bootstrap || !bootstrap.Modal) return;
    try {
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        massLabelLog('Modal hide requested (getOrCreateInstance)');
    } catch (err) {
        console.error('[MassLabel] Modal hide failed', err);
    }
}

function massLabelPayloadSummary(form) {
    const formData = new FormData(form);
    return {
        scope: formData.get('scope'),
        inventory_ids: formData.getAll('inventory_ids[]'),
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

/**
 * Backend returns Laravel PDF download (Content-Disposition: attachment).
 * Do NOT use fetch/axios/$.ajax — those break gesture-based downloads and
 * leave the page stuck when binary is mishandled. Native form POST → iframe
 * lets the browser start the file download itself.
 */
function downloadMassLabelPdf(form) {
    const iframe = document.getElementById('massLabelDownloadFrame');
    const payload = massLabelPayloadSummary(form);

    massLabelLog('Clicked → native form POST (not AJAX)');
    massLabelLog('URL:', form.action);
    massLabelLog('Method: POST');
    massLabelLog('Payload / filters / selected IDs:', payload);

    closeMassLabelsModal();
    startMassLabelLoading();

    if (iframe) {
        iframe.onload = function () {
            // Fires on HTML/redirect error pages. Attachment downloads often skip onload.
            massLabelLog('Iframe load event (response navigated into frame)');
            try {
                const doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
                if (doc && doc.body) {
                    const text = (doc.body.innerText || '').trim().slice(0, 200);
                    massLabelLog('Iframe body preview:', text || '(empty)');
                }
            } catch (e) {
                massLabelLog('Iframe body not readable', e);
            }
            stopMassLabelLoading('iframe-load');
        };
    }

    try {
        form.setAttribute('target', 'massLabelDownloadFrame');
        // Native submit — browser applies Content-Disposition: attachment → download starts.
        HTMLFormElement.prototype.submit.call(form);
        form.removeAttribute('target');
        massLabelLog('Download triggered: form.submit() → #massLabelDownloadFrame');
    } catch (err) {
        console.error('[MassLabel] Exception stack:', err);
        form.removeAttribute('target');
        stopMassLabelLoading('exception');
        alert('Gagal memulai unduhan label PDF.');
        return;
    }

    // Attachment responses frequently never fire iframe.onload — close overlay after short wait.
    // The download continues in the background regardless of overlay state.
    massLabelPostSubmitTimer = window.setTimeout(function () {
        massLabelPostSubmitTimer = null;
        stopMassLabelLoading('post-submit-timeout');
    }, 2500);
}

function submitMassLabels(scope) {
    syncMassFilterFields();
    $('#massLabelScope').val(scope);
    const $ids = $('#massLabelIds').empty();
    if (scope === 'selected') {
        if (selectedIds.size === 0) {
            alert('Tidak ada inventaris yang dipilih.');
            return;
        }
        selectedIds.forEach(function (id) {
            $ids.append(`<input type="hidden" name="inventory_ids[]" value="${id}">`);
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
    massLabelLog('Click: Cetak yang dipilih');
    submitMassLabels('selected');
});
$('#btnPrintFilterLabels').on('click', function (e) {
    e.preventDefault();
    massLabelLog('Click: Cetak semua hasil filter');
    submitMassLabels('filter');
});

$('#btnApplyFilter').on('click', function () {
    if (table) table.ajax.reload();
});
$('#btnResetFilter').on('click', function() {
    $('#filterForm')[0].reset();
    $('.select2-filter').val('').trigger('change');
    selectedIds.clear();
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
