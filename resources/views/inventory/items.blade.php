@extends('layouts.app')

@section('title', 'Semua Item Inventaris - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    <li class="breadcrumb-item active">Semua Item</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-list me-2"></i>Semua Item</h1>
        <p class="text-muted mb-0 small">Daftar flat dengan filter &amp; DataTables</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-university me-1"></i>Per Kampus</a>
        <a href="{{ route('inventory.categories') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-th-large me-1"></i>Per Kategori</a>
        @can('inventory.create')
        <a href="{{ route('categories.index', ['tab' => 'konfigurasi', 'create' => 1]) }}" class="btn btn-undira btn-sm"><i class="fas fa-plus me-1"></i>Tambah</a>
        @endcan
        @can('inventory.export')
        <div class="btn-group btn-group-sm">
            <a href="{{ route('inventory.export.excel') }}" class="btn btn-outline-success"><i class="fas fa-file-excel"></i></a>
            <a href="{{ route('inventory.export.csv') }}" class="btn btn-outline-success"><i class="fas fa-file-csv"></i></a>
            <a href="{{ route('inventory.export.pdf') }}" class="btn btn-outline-danger"><i class="fas fa-file-pdf"></i></a>
        </div>
        @endcan
    </div>
</div>

<div class="filter-panel">
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
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="button" id="btnApplyFilter" class="btn btn-undira btn-sm"><i class="fas fa-filter me-1"></i>Terapkan</button>
                <button type="button" id="btnResetFilter" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo me-1"></i>Reset</button>
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
                        <th>No. Inventaris</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>Harga</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const locationData = @json($locationData);
$('.select2-filter').select2({ theme: 'bootstrap-5', width: '100%' });

function populateSelect($select, items, valueKey, labelKey, placeholder) {
    $select.empty().append(`<option value="">${placeholder}</option>`);
    items.forEach(item => $select.append(`<option value="${item[valueKey]}">${item[labelKey]}</option>`));
    $select.trigger('change');
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

const table = $('#inventoryTable').DataTable({
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
            d.status = $('#filterStatus').val();
            d.condition = $('#filterCondition').val();
        }
    },
    columns: [
        { data: 'inventory_number', render: (data, type, row) => `<a href="/inventory/${row.id}">${data}</a>` },
        { data: 'name' },
        { data: 'category', render: c => c?.name || '-' },
        { data: 'full_location', render: d => `<small>${d || '-'}</small>` },
        { data: 'status', render: s => { const label = (typeof s === 'object' && s?.label) ? s.label : (s || '-'); return `<span class="badge bg-secondary">${label}</span>`; }},
        { data: 'condition', render: c => { const label = (typeof c === 'object' && c?.label) ? c.label : (c || '-'); return `<span class="badge bg-info">${label}</span>`; }},
        { data: 'price', render: p => p ? 'Rp ' + Number(p).toLocaleString('id-ID') : '-' },
        { data: null, orderable: false, render: (data, type, row) => `
            <a href="/inventory/${row.id}" class="btn btn-xs btn-outline-primary btn-sm"><i class="fas fa-eye"></i></a>
            @can('inventory.update')<a href="/inventory/${row.id}/edit" class="btn btn-xs btn-outline-warning btn-sm"><i class="fas fa-edit"></i></a>@endcan
        `}
    ],
    order: [[0, 'asc']],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/id.json' }
});

$('#btnApplyFilter').on('click', () => table.ajax.reload());
$('#btnResetFilter').on('click', function() {
    $('#filterForm')[0].reset();
    $('.select2-filter').val('').trigger('change');
    table.ajax.reload();
});
</script>
@endpush
