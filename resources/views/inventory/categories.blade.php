@extends('layouts.app')

@section('title', 'Inventaris per Kategori - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    <li class="breadcrumb-item active">Per Kategori</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-th-large me-2"></i>Inventaris per Kategori</h1>
        <p class="text-muted mb-0 small">Pilih kategori untuk menelusuri Kampus → Gedung → Lantai → Ruangan</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('inventory.create')
        <a href="{{ route('categories.index', ['tab' => 'konfigurasi', 'create' => 1]) }}" class="btn btn-undira btn-sm"><i class="fas fa-sliders-h me-1"></i>Konfigurasi Inventaris</a>
        @endcan
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-university me-1"></i>Per Kampus</a>
        <a href="{{ route('inventory.items') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>Semua Item</a>
    </div>
</div>

<div class="filter-panel">
    <form method="GET" action="{{ route('inventory.categories') }}" id="filterForm">
        <div class="row g-2 g-md-3">
            <div class="col-12 col-md-4">
                <label class="form-label small">Cari Kategori</label>
                <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Nama / kode kategori...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Kampus</label>
                <select name="campus_id" id="filterCampus" class="form-select">
                    <option value="">Semua</option>
                    @foreach($locationData as $campus)
                        <option value="{{ $campus->id }}" @selected(($filters['campus_id'] ?? '') == $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Gedung</label>
                <select name="building_id" id="filterBuilding" class="form-select">
                    <option value="">Semua</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Lantai</label>
                <select name="floor_id" id="filterFloor" class="form-select">
                    <option value="">Semua</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Ruangan</label>
                <select name="room_id" id="filterRoom" class="form-select">
                    <option value="">Semua</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Urutkan</label>
                <select name="sort" class="form-select">
                    <option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>Nama</option>
                    <option value="code" @selected(($filters['sort'] ?? '') === 'code')>Kode</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Arah</label>
                <select name="dir" class="form-select">
                    <option value="asc" @selected(($filters['dir'] ?? 'asc') === 'asc')>A–Z</option>
                    <option value="desc" @selected(($filters['dir'] ?? '') === 'desc')>Z–A</option>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-undira btn-sm"><i class="fas fa-filter me-1"></i>Terapkan</button>
                <a href="{{ route('inventory.categories') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo me-1"></i>Reset</a>
            </div>
        </div>
    </form>
</div>

{{-- Desktop table --}}
<div class="card card-undira d-none d-md-block">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Kode</th>
                        <th class="text-end">Unit</th>
                        <th class="text-end">Item</th>
                        <th class="text-end">Kampus</th>
                        <th class="text-end">Gedung</th>
                        <th class="text-end">Ruangan</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    @php $s = $stats[$category->id] ?? ['total_units'=>0,'total_items'=>0,'total_campuses'=>0,'total_buildings'=>0,'total_rooms'=>0]; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('inventory.category', $category) }}" class="fw-semibold text-decoration-none">
                                {{ $category->name }}
                            </a>
                        </td>
                        <td><code>{{ $category->code }}</code></td>
                        <td class="text-end">{{ number_format($s['total_units']) }}</td>
                        <td class="text-end">{{ number_format($s['total_items']) }}</td>
                        <td class="text-end">{{ number_format($s['total_campuses']) }}</td>
                        <td class="text-end">{{ number_format($s['total_buildings']) }}</td>
                        <td class="text-end">{{ number_format($s['total_rooms']) }}</td>
                        <td class="text-end">
                            <a href="{{ route('inventory.category', $category) }}" class="btn btn-sm btn-undira">
                                <i class="fas fa-folder-open me-1"></i>Buka
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada kategori.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($categories->hasPages())
    <div class="card-footer">{{ $categories->links() }}</div>
    @endif
</div>

{{-- Mobile cards --}}
<div class="d-md-none">
    <div class="row g-3">
        @forelse($categories as $category)
        @php $s = $stats[$category->id] ?? ['total_units'=>0,'total_items'=>0,'total_campuses'=>0,'total_buildings'=>0,'total_rooms'=>0]; @endphp
        <div class="col-12">
            <a href="{{ route('inventory.category', $category) }}" class="text-decoration-none">
                <div class="card card-undira category-card-mobile h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-0 text-undira-blue">{{ $category->name }}</h5>
                                <code class="small">{{ $category->code }}</code>
                            </div>
                            <span class="badge bg-success">{{ number_format($s['total_units']) }} Unit</span>
                        </div>
                        <div class="row g-2 small text-muted">
                            <div class="col-4"><i class="fas fa-cube me-1"></i>{{ $s['total_items'] }} Item</div>
                            <div class="col-4"><i class="fas fa-university me-1"></i>{{ $s['total_campuses'] }} Kampus</div>
                            <div class="col-4"><i class="fas fa-building me-1"></i>{{ $s['total_buildings'] }} Gedung</div>
                            <div class="col-12"><i class="fas fa-door-open me-1"></i>{{ $s['total_rooms'] }} Ruangan</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        @empty
        <div class="col-12"><div class="alert alert-light text-center">Tidak ada kategori.</div></div>
        @endforelse
    </div>
    @if($categories->hasPages())
    <div class="mt-3">{{ $categories->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
const locationData = @json($locationData);
const selected = {
    campus: @json($filters['campus_id'] ?? null),
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
    return locationData.find(c => String(c.id) === String($('#filterCampus').val())) || null;
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

$('#filterCampus').on('change', function () {
    selected.campus = $(this).val() || null;
    selected.building = selected.floor = selected.room = null;
    const campus = getCampus();
    populate($('#filterBuilding'), campus?.buildings || [], 'Semua', null);
    populate($('#filterFloor'), [], 'Semua', null);
    populate($('#filterRoom'), [], 'Semua', null);
});
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
