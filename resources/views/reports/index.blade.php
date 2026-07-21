@extends('layouts.app')

@section('title', 'Laporan - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Laporan</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-chart-bar me-2"></i>Generate Laporan</h1>
    <p class="text-muted mb-0">Laporan hierarkis dari data inventaris yang sama dengan Dashboard &amp; Data Inventaris.</p>
</div>

<form action="{{ route('reports.generate') }}" method="POST" id="reportForm" target="_blank">
    @csrf
    <div class="card card-undira">
        <div class="card-header"><h5 class="mb-0">Filter Laporan</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="campus_id">Kampus</label>
                    <select name="campus_id" id="campus_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="building_id">Gedung</label>
                    <select name="building_id" id="building_id" class="form-select" disabled>
                        <option value="">Semua</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="floor_id">Lantai</label>
                    <select name="floor_id" id="floor_id" class="form-select" disabled>
                        <option value="">Semua</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="room_id">Ruangan</label>
                    <select name="room_id" id="room_id" class="form-select" disabled>
                        <option value="">Semua</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="category_id">Kategori</label>
                    <select name="category_id" id="category_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="sub_category_id">Sub Kategori</label>
                    <select name="sub_category_id" id="sub_category_id" class="form-select" disabled>
                        <option value="">Semua</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach(\App\Enums\InventoryStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="condition">Kondisi</label>
                    <select name="condition" id="condition" class="form-select">
                        <option value="">Semua</option>
                        @foreach(\App\Enums\InventoryCondition::cases() as $cond)
                            <option value="{{ $cond->value }}">{{ $cond->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="purchase_year">Tahun Beli</label>
                    <input type="number" name="purchase_year" id="purchase_year" class="form-control" min="1900" max="2100">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="purchase_date_from">Tgl Beli Dari</label>
                    <input type="date" name="purchase_date_from" id="purchase_date_from" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="purchase_date_to">Tgl Beli Sampai</label>
                    <input type="date" name="purchase_date_to" id="purchase_date_to" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="search">Pencarian</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Nama, no. inventaris...">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="format">Format Output <span class="text-danger">*</span></label>
                    <select name="format" id="format" class="form-select" required>
                        <option value="view">Tampil di Browser</option>
                        <option value="print">Cetak (Print)</option>
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel (.xlsx)</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="include_trash" value="1" id="include_trash" class="form-check-input" @checked(old('include_trash'))>
                        <label class="form-check-label" for="include_trash">Sertakan Data Trash</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-undira"><i class="fas fa-file-alt me-1"></i>Generate Laporan</button>
            <button type="button" class="btn btn-outline-secondary" id="btnResetFilters"><i class="fas fa-undo me-1"></i>Reset Filter</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
@php
    $subCategoryMap = \App\Models\SubCategory::where('is_active', true)->orderBy('name')->get(['id', 'category_id', 'name', 'code']);
@endphp
<script>
(function () {
    const buildingsUrl = (id) => @json(url('/master-data/campuses')) + '/' + id + '/buildings';
    const floorsUrl = (id) => @json(url('/master-data/buildings')) + '/' + id + '/floors';
    const roomsUrl = (id) => @json(url('/master-data/floors')) + '/' + id + '/rooms';
    const subCategoryMap = @json($subCategoryMap->groupBy('category_id'));

    function fillSelect($el, items, placeholder, selectedId, labelFn) {
        $el.prop('disabled', false).empty().append(`<option value="">${placeholder}</option>`);
        (items || []).forEach(item => {
            const label = labelFn ? labelFn(item) : (item.code ? `${item.name} (${item.code})` : item.name);
            $el.append(`<option value="${item.id}">${label}</option>`);
        });
    }

    function resetSelect($el, placeholder) {
        $el.prop('disabled', true).empty().append(`<option value="">${placeholder}</option>`);
    }

    async function fetchJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('Gagal memuat data');
        return res.json();
    }

    async function loadBuildings() {
        const campusId = $('#campus_id').val();
        resetSelect($('#floor_id'), 'Semua');
        resetSelect($('#room_id'), 'Semua');
        if (!campusId) {
            resetSelect($('#building_id'), 'Semua');
            return;
        }
        $('#building_id').prop('disabled', true).html('<option value="">Memuat…</option>');
        try {
            const json = await fetchJson(buildingsUrl(campusId));
            fillSelect($('#building_id'), json.data, 'Semua');
        } catch (e) {
            fillSelect($('#building_id'), [], 'Gagal memuat');
        }
    }

    async function loadFloors() {
        const buildingId = $('#building_id').val();
        resetSelect($('#room_id'), 'Semua');
        if (!buildingId) {
            resetSelect($('#floor_id'), 'Semua');
            return;
        }
        $('#floor_id').prop('disabled', true).html('<option value="">Memuat…</option>');
        try {
            const json = await fetchJson(floorsUrl(buildingId));
            fillSelect($('#floor_id'), json.data, 'Semua');
        } catch (e) {
            fillSelect($('#floor_id'), [], 'Gagal memuat');
        }
    }

    async function loadRooms() {
        const floorId = $('#floor_id').val();
        if (!floorId) {
            resetSelect($('#room_id'), 'Semua');
            return;
        }
        $('#room_id').prop('disabled', true).html('<option value="">Memuat…</option>');
        try {
            const json = await fetchJson(roomsUrl(floorId));
            fillSelect($('#room_id'), json.data, 'Semua', null, (item) => `${item.code} — ${item.name}`);
        } catch (e) {
            fillSelect($('#room_id'), [], 'Gagal memuat');
        }
    }

    function loadSubs() {
        const catId = $('#category_id').val();
        if (!catId) {
            resetSelect($('#sub_category_id'), 'Semua');
            return;
        }
        const items = subCategoryMap[String(catId)] || [];
        fillSelect($('#sub_category_id'), items, 'Semua');
    }

    $('#campus_id').on('change', loadBuildings);
    $('#building_id').on('change', loadFloors);
    $('#floor_id').on('change', loadRooms);
    $('#category_id').on('change', loadSubs);

    $('#btnResetFilters').on('click', function () {
        $('#reportForm')[0].reset();
        resetSelect($('#building_id'), 'Semua');
        resetSelect($('#floor_id'), 'Semua');
        resetSelect($('#room_id'), 'Semua');
        resetSelect($('#sub_category_id'), 'Semua');
    });
})();
</script>
@endpush
