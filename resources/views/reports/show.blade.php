@extends('layouts.app')

@section('title', $type->label().' - Laporan UNDIRA')

@section('content')
<x-page-header
    :title="$type->label()"
    :subtitle="$type->description()"
    :icon="$type->icon()"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Laporan', 'url' => route('reports.index')],
        ['label' => $type->label()],
    ]"
    :back-route="route('reports.index')"
    back-label="Dashboard Laporan"
/>

@include('reports.partials.filters', ['type' => $type, 'options' => $options])

<div class="card card-undira">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong>Hasil Preview</strong>
        <div class="small text-muted" id="reportSummaryText">Siap memuat data.</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover table-striped mb-0 align-middle" id="reportTable">
                <thead class="table-light" id="reportThead"></thead>
                <tbody id="reportTbody">
                    <tr><td class="text-center text-muted py-4">Gunakan filter lalu klik Preview, atau ketik keyword untuk pencarian realtime.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small" id="reportPageInfo"></div>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" id="btnPrevPage" disabled>Prev</button>
            <button type="button" class="btn btn-outline-secondary" id="btnNextPage" disabled>Next</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
    $subMap = $options['subCategories']->groupBy('category_id');
    $family = $type->family();
@endphp
<script>
(function () {
    const type = @json($type->value);
    const family = @json($family);
    const dataUrl = @json(route('reports.data', ['type' => $type->value]));
    const exportUrl = @json(route('reports.export', ['type' => $type->value]));
    const csrf = @json(csrf_token());
    const subCategoryMap = @json($subMap);
    const buildingsUrl = (id) => @json(url('/master-data/campuses')) + '/' + id + '/buildings';
    const floorsUrl = (id) => @json(url('/master-data/buildings')) + '/' + id + '/floors';
    const roomsUrl = (id) => @json(url('/master-data/floors')) + '/' + id + '/rooms';

    let page = 1;
    let lastPage = 1;
    let debounceTimer = null;

    const columnsByFamily = {
        inventory: [
            {key:'no', label:'No'},
            {key:'qr', label:'QR', html:true},
            {key:'inventory_number', label:'Nomor Inventaris'},
            {key:'name', label:'Nama Barang'},
            {key:'category', label:'Kategori'},
            {key:'sub_category', label:'Subkategori'},
            {key:'brand', label:'Merk'},
            {key:'serial_number', label:'Serial'},
            {key:'location', label:'Lokasi'},
            {key:'pic', label:'PIC'},
            {key:'status', label:'Status'},
            {key:'condition', label:'Kondisi'},
            {key:'purchase_date', label:'Tgl Pengadaan'},
        ],
        borrowing: [
            {key:'no', label:'No'},
            {key:'borrowing_number', label:'No Peminjaman'},
            {key:'borrower', label:'Peminjam'},
            {key:'item', label:'Barang'},
            {key:'borrow_date', label:'Tgl Pinjam'},
            {key:'return_date', label:'Tgl Kembali'},
            {key:'status', label:'Status'},
            {key:'duration', label:'Durasi'},
        ],
        maintenance: [
            {key:'no', label:'No'},
            {key:'wo', label:'No WO'},
            {key:'inventory_number', label:'No Inventaris'},
            {key:'name', label:'Barang'},
            {key:'location', label:'Lokasi'},
            {key:'service_date', label:'Tgl Masuk'},
            {key:'started_at', label:'Mulai'},
            {key:'completed_at', label:'Selesai'},
            {key:'technician', label:'Teknisi'},
            {key:'status', label:'Status'},
            {key:'duration', label:'Durasi'},
            {key:'cost', label:'Biaya'},
        ],
        mutation: [
            {key:'no', label:'No'},
            {key:'item', label:'Barang'},
            {key:'from', label:'Lokasi Lama'},
            {key:'to', label:'Lokasi Baru'},
            {key:'date', label:'Tanggal'},
            {key:'pic_new', label:'PIC'},
            {key:'moved_by', label:'Petugas'},
            {key:'reason', label:'Alasan'},
        ],
    };

    const cols = columnsByFamily[family] || columnsByFamily.inventory;

    function renderHead() {
        $('#reportThead').html('<tr>' + cols.map(c => `<th>${c.label}</th>`).join('') + '</tr>');
    }

    function fillSelect($el, items, placeholder) {
        $el.prop('disabled', false).empty().append(`<option value="">${placeholder}</option>`);
        (items || []).forEach(item => {
            const label = item.code ? `${item.name} (${item.code})` : item.name;
            $el.append(`<option value="${item.id}">${label}</option>`);
        });
    }
    function resetSelect($el, placeholder) {
        $el.prop('disabled', true).empty().append(`<option value="">${placeholder}</option>`);
    }
    async function fetchJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('Gagal memuat');
        return res.json();
    }

    async function loadBuildings() {
        const id = $('#campus_id').val();
        resetSelect($('#floor_id'), 'Semua');
        resetSelect($('#room_id'), 'Semua');
        if (!id) return resetSelect($('#building_id'), 'Semua');
        try { fillSelect($('#building_id'), (await fetchJson(buildingsUrl(id))).data, 'Semua'); }
        catch { fillSelect($('#building_id'), [], 'Gagal'); }
    }
    async function loadFloors() {
        const id = $('#building_id').val();
        resetSelect($('#room_id'), 'Semua');
        if (!id) return resetSelect($('#floor_id'), 'Semua');
        try { fillSelect($('#floor_id'), (await fetchJson(floorsUrl(id))).data, 'Semua'); }
        catch { fillSelect($('#floor_id'), [], 'Gagal'); }
    }
    async function loadRooms() {
        const id = $('#floor_id').val();
        if (!id) return resetSelect($('#room_id'), 'Semua');
        try {
            fillSelect($('#room_id'), (await fetchJson(roomsUrl(id))).data, 'Semua');
            $('#room_id option').each(function () {
                // keep
            });
            // nicer labels already from API
        } catch { fillSelect($('#room_id'), [], 'Gagal'); }
    }
    function loadSubs() {
        const catId = $('#category_id').val();
        if (!catId) return resetSelect($('#sub_category_id'), 'Semua');
        fillSelect($('#sub_category_id'), subCategoryMap[String(catId)] || [], 'Semua');
    }

    function formParams(extra = {}) {
        const data = new FormData(document.getElementById('reportFilterForm'));
        const params = new URLSearchParams();
        for (const [k, v] of data.entries()) {
            if (v !== '') params.append(k, v);
        }
        Object.entries(extra).forEach(([k, v]) => params.set(k, v));
        // map tanggal to purchase_date for inventory filter compatibility
        if (params.has('tanggal_awal')) params.set('purchase_date_from', params.get('tanggal_awal'));
        if (params.has('tanggal_akhir')) params.set('purchase_date_to', params.get('tanggal_akhir'));
        if (params.has('keyword')) params.set('search', params.get('keyword'));
        return params;
    }

    async function loadData(p = 1) {
        page = p;
        $('#reportLoading').show();
        try {
            const params = formParams({ page, per_page: 50 });
            const res = await fetch(dataUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('Gagal memuat laporan');
            const json = await res.json();
            lastPage = json.meta?.last_page || 1;
            const rows = json.data || [];
            if (!rows.length) {
                $('#reportTbody').html(`<tr><td colspan="${cols.length}" class="text-center text-muted py-4">Tidak ada data.</td></tr>`);
            } else {
                $('#reportTbody').html(rows.map(row => {
                    return '<tr>' + cols.map(c => {
                        let val = row[c.key] ?? '—';
                        if (c.key === 'qr') {
                            val = row.qr ? `<img src="${row.qr}" alt="QR" width="28" height="28">` : '—';
                        }
                        return `<td>${val}</td>`;
                    }).join('') + '</tr>';
                }).join(''));
            }
            $('#reportSummaryText').text(`Total: ${json.meta?.total ?? 0}`);
            $('#reportPageInfo').text(`Halaman ${json.meta?.current_page || 1} / ${lastPage}`);
            $('#btnPrevPage').prop('disabled', page <= 1);
            $('#btnNextPage').prop('disabled', page >= lastPage);
        } catch (e) {
            $('#reportTbody').html(`<tr><td colspan="${cols.length}" class="text-danger text-center py-4">${e.message}</td></tr>`);
        } finally {
            $('#reportLoading').hide();
        }
    }

    function submitExport(format) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = exportUrl;
        form.target = '_blank';
        const extra = { format, _token: csrf };
        if (format === 'pdf-landscape') extra.orientation = 'landscape';
        if (format === 'pdf-portrait' || format === 'pdf') extra.orientation = 'portrait';
        const params = formParams(extra);
        for (const [k, v] of params.entries()) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = k;
            input.value = v;
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    $('#campus_id').on('change', () => { loadBuildings(); scheduleLoad(); });
    $('#building_id').on('change', () => { loadFloors(); scheduleLoad(); });
    $('#floor_id').on('change', () => { loadRooms(); scheduleLoad(); });
    $('#category_id').on('change', () => { loadSubs(); scheduleLoad(); });
    $('#building_id,#floor_id,#room_id,#sub_category_id,#pic_id,#status,#condition,#procurement_type_id,#vendor_id,#supplier_id,#include_trash').on('change', scheduleLoad);
    $('#purchase_year,#tanggal_awal,#tanggal_akhir,#inventory_number,#item_name,#serial_number,#merk,#keyword').on('input', scheduleLoad);

    function scheduleLoad() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadData(1), 350);
    }

    $('#btnPreview').on('click', () => loadData(1));
    $('#btnPrevPage').on('click', () => loadData(Math.max(1, page - 1)));
    $('#btnNextPage').on('click', () => loadData(Math.min(lastPage, page + 1)));
    $('#btnPrint').on('click', () => submitExport('print'));
    $('#btnPdfPortrait').on('click', () => submitExport('pdf-portrait'));
    $('#btnPdfLandscape').on('click', () => submitExport('pdf-landscape'));
    $('#btnExcel').on('click', () => submitExport('excel'));
    $('#btnCsv').on('click', () => submitExport('csv'));
    $('#btnResetReportFilters').on('click', function () {
        $('#reportFilterForm')[0].reset();
        resetSelect($('#building_id'), 'Semua');
        resetSelect($('#floor_id'), 'Semua');
        resetSelect($('#room_id'), 'Semua');
        resetSelect($('#sub_category_id'), 'Semua');
        loadData(1);
    });

    renderHead();
    loadData(1);
})();
</script>
@endpush
