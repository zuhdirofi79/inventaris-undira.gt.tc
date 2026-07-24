@extends('layouts.app')

@section('title', 'Rekapitulasi Inventaris - UNDIRA')

@section('content')
<x-page-header
    title="Rekapitulasi Inventaris"
    subtitle="KPI dan grafik agregat — tanpa memuat seluruh baris inventaris ke memori"
    icon="fas fa-chart-pie"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Laporan', 'url' => route('reports.index')],
        ['label' => 'Rekapitulasi'],
    ]"
    :back-route="route('reports.index')"
    back-label="Dashboard Laporan"
/>

@include('reports.partials.filters', ['type' => $type, 'options' => $options])

@php $cards = $dashboard['cards'] ?? []; $charts = $dashboard['charts'] ?? []; @endphp

<div class="row g-3 mb-3" id="recapCards">
    @foreach([
        ['Total Inventaris', $cards['total'] ?? 0, ''],
        ['Aktif', $cards['aktif'] ?? 0, 'text-success'],
        ['Dipinjam', $cards['dipinjam'] ?? 0, 'text-primary'],
        ['Rusak', $cards['rusak'] ?? 0, 'text-danger'],
        ['Maintenance', $cards['maintenance'] ?? 0, 'text-warning'],
        ['Non Aktif', $cards['non_aktif'] ?? 0, ''],
    ] as [$label, $val, $cls])
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-undira h-100"><div class="card-body py-3">
            <div class="small text-muted text-uppercase fw-bold">{{ $label }}</div>
            <div class="fs-4 fw-bold {{ $cls }}">{{ number_format($val) }}</div>
        </div></div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-6"><div class="card card-undira"><div class="card-header">Inventaris per Kampus</div><div class="card-body"><canvas id="chartCampus" height="220"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card card-undira"><div class="card-header">Inventaris per Kategori</div><div class="card-body"><canvas id="chartCategory" height="220"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card card-undira"><div class="card-header">Inventaris per Kondisi</div><div class="card-body"><canvas id="chartCondition" height="220"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card card-undira"><div class="card-header">Inventaris per Tahun</div><div class="card-body"><canvas id="chartYear" height="220"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card card-undira"><div class="card-header">Inventaris per Pengadaan</div><div class="card-body"><canvas id="chartProcurement" height="220"></canvas></div></div></div>
</div>
@endsection

@push('scripts')
@php $subMap = $options['subCategories']->groupBy('category_id'); @endphp
<script>
(function () {
    const subCategoryMap = @json($subMap);
    const buildingsUrl = (id) => @json(url('/master-data/campuses')) + '/' + id + '/buildings';
    const floorsUrl = (id) => @json(url('/master-data/buildings')) + '/' + id + '/floors';
    const roomsUrl = (id) => @json(url('/master-data/floors')) + '/' + id + '/rooms';
    function fillSelect($el, items, placeholder) {
        $el.prop('disabled', false).empty().append(`<option value="">${placeholder}</option>`);
        (items || []).forEach(item => $el.append(`<option value="${item.id}">${item.code ? item.name+' ('+item.code+')' : item.name}</option>`));
    }
    function resetSelect($el, placeholder) { $el.prop('disabled', true).empty().append(`<option value="">${placeholder}</option>`); }
    async function fetchJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('fail');
        return res.json();
    }
    $('#campus_id').on('change', async function () {
        resetSelect($('#floor_id'), 'Semua'); resetSelect($('#room_id'), 'Semua');
        if (!this.value) return resetSelect($('#building_id'), 'Semua');
        try { fillSelect($('#building_id'), (await fetchJson(buildingsUrl(this.value))).data, 'Semua'); } catch { fillSelect($('#building_id'), [], 'Gagal'); }
    });
    $('#building_id').on('change', async function () {
        resetSelect($('#room_id'), 'Semua');
        if (!this.value) return resetSelect($('#floor_id'), 'Semua');
        try { fillSelect($('#floor_id'), (await fetchJson(floorsUrl(this.value))).data, 'Semua'); } catch { fillSelect($('#floor_id'), [], 'Gagal'); }
    });
    $('#floor_id').on('change', async function () {
        if (!this.value) return resetSelect($('#room_id'), 'Semua');
        try { fillSelect($('#room_id'), (await fetchJson(roomsUrl(this.value))).data, 'Semua'); } catch { fillSelect($('#room_id'), [], 'Gagal'); }
    });
    $('#category_id').on('change', function () {
        const items = subCategoryMap[String(this.value)] || [];
        if (!this.value) return resetSelect($('#sub_category_id'), 'Semua');
        fillSelect($('#sub_category_id'), items, 'Semua');
    });

    const chartsData = @json($charts);
    const palette = ['#1F3C88','#0d6efd','#198754','#dc3545','#fd7e14','#6f42c1','#20c997','#6610f2','#0dcaf0','#6c757d'];
    function makeBar(id, rows) {
        const labels = (rows || []).map(r => r.label);
        const values = (rows || []).map(r => Number(r.total));
        return new Chart(document.getElementById(id), {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Jumlah', data: values, backgroundColor: palette }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }
    function makeDoughnut(id, rows) {
        const labels = (rows || []).map(r => r.label);
        const values = (rows || []).map(r => Number(r.total));
        return new Chart(document.getElementById(id), {
            type: 'doughnut',
            data: { labels, datasets: [{ data: values, backgroundColor: palette }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
    makeBar('chartCampus', chartsData.campus);
    makeBar('chartCategory', chartsData.category);
    makeDoughnut('chartCondition', chartsData.condition);
    makeBar('chartYear', chartsData.year);
    makeBar('chartProcurement', chartsData.procurement);

    $('#btnPrint,#btnPdfPortrait,#btnPdfLandscape,#btnExcel,#btnCsv').hide();
    $('#btnResetReportFilters').on('click', function () {
        window.location = @json(route('reports.show', 'rekapitulasi'));
    });
    $('#btnPreview').off('click').on('click', function () {
        const data = new FormData(document.getElementById('reportFilterForm'));
        const params = new URLSearchParams();
        for (const [k, v] of data.entries()) if (v !== '') params.append(k, v);
        window.location = @json(route('reports.show', 'rekapitulasi')) + '?' + params.toString();
    });
})();
</script>
@endpush

