@extends('layouts.app')

@section('title', $campus->name . ' - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    <li class="breadcrumb-item active">{{ $campus->name }}</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-university me-2"></i>{{ $campus->name }}</h1>
        <p class="text-muted mb-0 small">Gedung → Lantai → Ruangan → Unit inventaris</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('inventory.create')
        <a href="{{ route('categories.index', ['tab' => 'konfigurasi', 'create' => 1]) }}" class="btn btn-undira btn-sm"><i class="fas fa-plus me-1"></i>Tambah</a>
        @endcan
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Kampus</a>
    </div>
</div>

<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-3"><div class="card card-undira card-stat"><div class="card-body py-3"><div class="text-muted small">Total Unit</div><div class="fs-4 fw-bold">{{ number_format($summary['total_units']) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-undira card-stat stat-blue"><div class="card-body py-3"><div class="text-muted small">Grup Item</div><div class="fs-4 fw-bold">{{ number_format($summary['total_groups']) }}</div></div></div></div>
    <div class="col-4 col-md-2"><div class="card card-undira"><div class="card-body py-3"><div class="text-muted small">Gedung</div><div class="fs-5 fw-bold">{{ $summary['buildings'] }}</div></div></div></div>
    <div class="col-4 col-md-2"><div class="card card-undira"><div class="card-body py-3"><div class="text-muted small">Lantai</div><div class="fs-5 fw-bold">{{ $summary['floors'] }}</div></div></div></div>
    <div class="col-4 col-md-2"><div class="card card-undira"><div class="card-body py-3"><div class="text-muted small">Ruangan</div><div class="fs-5 fw-bold">{{ $summary['rooms'] }}</div></div></div></div>
</div>

<div class="filter-panel">
    <form method="GET" action="{{ route('inventory.campus', $campus) }}" id="filterForm">
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <label class="form-label small">Cari</label>
                <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Nama / kode / no. inventaris...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Gedung</label>
                <select name="building_id" id="filterBuilding" class="form-select"><option value="">Semua</option></select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Lantai</label>
                <select name="floor_id" id="filterFloor" class="form-select"><option value="">Semua</option></select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Ruangan</label>
                <select name="room_id" id="filterRoom" class="form-select"><option value="">Semua</option></select>
            </div>
            <div class="col-6 col-md-2 d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-undira btn-sm flex-grow-1"><i class="fas fa-filter"></i></button>
                <a href="{{ route('inventory.campus', $campus) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i></a>
            </div>
        </div>
    </form>
</div>

@php $campusNode = $tree[$campus->id] ?? null; @endphp

@if(! $campusNode)
<div class="alert alert-light border text-center">Belum ada inventaris di kampus ini{{ request()->hasAny(['search','building_id','floor_id','room_id']) ? ' dengan filter aktif' : '' }}.</div>
@else
<div class="accordion inventory-tree" id="buildingAccordion">
    @foreach($campusNode['buildings'] as $bId => $buildingNode)
    <div class="accordion-item mb-2 border rounded overflow-hidden">
        <h2 class="accordion-header">
            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#building-{{ $bId }}">
                <span class="me-auto"><i class="fas fa-building me-2 text-undira-blue"></i><strong>{{ $buildingNode['building']->name }}</strong></span>
                <span class="badge bg-secondary me-3">{{ $buildingNode['unit_count'] }} unit</span>
            </button>
        </h2>
        <div id="building-{{ $bId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#buildingAccordion">
            <div class="accordion-body p-2 p-md-3">
                @foreach($buildingNode['floors'] as $fId => $floorNode)
                <div class="mb-3">
                    <div class="fw-semibold mb-2 px-1">
                        <i class="fas fa-layer-group me-1 text-muted"></i>{{ $floorNode['floor']->name }}
                        <span class="text-muted small">({{ $floorNode['unit_count'] }} unit)</span>
                    </div>
                    @foreach($floorNode['rooms'] as $rId => $roomNode)
                    <div class="card card-undira mb-2">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-1">
                            <span><i class="fas fa-door-open me-1"></i>{{ $roomNode['room']->name }} <code class="small">{{ $roomNode['room']->code }}</code></span>
                            <span class="badge bg-success">{{ $roomNode['unit_count'] }} unit</span>
                        </div>
                        <div class="card-body p-0">
                            @include('inventory._unit-table', ['groups' => $roomNode['groups']])
                        </div>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection

@push('scripts')
<script>
const locationData = @json($locationData);
const selected = {
    building: @json($filters['building_id'] ?? null),
    floor: @json($filters['floor_id'] ?? null),
    room: @json($filters['room_id'] ?? null),
};
function populate($el, items, placeholder, selectedId) {
    $el.empty().append(`<option value="">${placeholder}</option>`);
    (items || []).forEach(item => {
        $el.append(`<option value="${item.id}" ${String(item.id) === String(selectedId || '') ? 'selected' : ''}>${item.name}</option>`);
    });
}
function getCampus() {
    return locationData[0] || null;
}
function getBuilding() {
    const campus = getCampus();
    return campus?.buildings?.find(b => String(b.id) === String($('#filterBuilding').val())) || null;
}
function getFloor() {
    const building = getBuilding();
    return building?.floors?.find(f => String(f.id) === String($('#filterFloor').val())) || null;
}
function initFilters() {
    const campus = getCampus();
    populate($('#filterBuilding'), campus?.buildings || [], 'Semua', selected.building);
    const building = getBuilding();
    populate($('#filterFloor'), building?.floors || [], 'Semua', selected.floor);
    const floor = getFloor();
    populate($('#filterRoom'), floor?.rooms || [], 'Semua', selected.room);
}
$('#filterBuilding').on('change', function () {
    selected.building = $(this).val() || null;
    selected.floor = selected.room = null;
    const building = getBuilding();
    populate($('#filterFloor'), building?.floors || [], 'Semua', null);
    populate($('#filterRoom'), [], 'Semua', null);
});
$('#filterFloor').on('change', function () {
    selected.floor = $(this).val() || null;
    selected.room = null;
    const floor = getFloor();
    populate($('#filterRoom'), floor?.rooms || [], 'Semua', null);
});
initFilters();
</script>
@endpush
