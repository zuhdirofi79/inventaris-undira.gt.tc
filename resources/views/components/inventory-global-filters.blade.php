{{-- Reusable global inventory filters (location + master + status/condition) --}}
@props([
    'action',
    'campuses' => collect(),
    'categories' => collect(),
    'orgUnits' => collect(),
    'showOrgUnit' => true,
])

@php
    $filters = request()->only([
        'q', 'search', 'campus_id', 'building_id', 'floor_id', 'room_id',
        'org_unit_id', 'category_id', 'sub_category_id', 'status', 'condition',
    ]);
@endphp

<form method="GET" action="{{ $action }}" class="card card-undira mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1">Cari</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? ($filters['search'] ?? '') }}" class="form-control form-control-sm" placeholder="Nama / nomor inventaris…">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Kampus</label>
                <select name="campus_id" id="gf_campus" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}" @selected(($filters['campus_id'] ?? '') == $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Kategori</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($showOrgUnit)
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Unit Kerja</label>
                <select name="org_unit_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($orgUnits as $unit)
                        <option value="{{ $unit->id }}" @selected(($filters['org_unit_id'] ?? '') == $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-md-1">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach(\App\Enums\InventoryStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small mb-1">Kondisi</label>
                <select name="condition" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach(\App\Enums\InventoryCondition::cases() as $condition)
                        <option value="{{ $condition->value }}" @selected(($filters['condition'] ?? '') === $condition->value)>{{ $condition->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-undira btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ $action }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
        {{ $slot }}
    </div>
</form>
