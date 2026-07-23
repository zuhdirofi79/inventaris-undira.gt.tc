@extends('layouts.app')

@section('title', 'Edit Inventaris - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    @if($inventory->category)
    <li class="breadcrumb-item"><a href="{{ route('inventory.category', $inventory->category) }}">{{ $inventory->category->name }}</a></li>
    @endif
    <li class="breadcrumb-item"><a href="{{ route('inventory.show', $inventory) }}">{{ $inventory->inventory_number }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-edit me-2"></i>Edit Inventaris</h1>
    <p class="text-muted mb-0"><code>{{ $inventory->inventory_number }}</code> &middot; {{ $inventory->name }}</p>
</div>

<div class="alert alert-info border-0 shadow-sm">
    Lokasi inventaris tidak diubah dari halaman ini.
    Gunakan <a href="{{ route('mutations.create', ['inventory_item_id' => $inventory->id]) }}" class="alert-link">Mutasi Inventaris</a>
    agar riwayat perpindahan tersimpan.
    <div class="small mt-1 mb-0"><strong>Lokasi saat ini:</strong> {{ $inventory->full_location }}</div>
</div>

<form action="{{ route('inventory.update', $inventory) }}" method="POST" class="inventory-form">
    @csrf
    @method('PUT')
    @include('inventory._form', ['inventory' => $inventory, 'lockLocation' => true])
    <div class="d-flex flex-wrap gap-2 sticky-form-actions">
        <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Perbarui</button>
        <a href="{{ route('inventory.show', $inventory) }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection

@push('scripts')
@include('inventory._location-cascade-script', [
    'selectedCampusId' => old('campus_id', $inventory->room?->floor?->building?->campus_id),
    'selectedBuildingId' => old('building_id', $inventory->room?->floor?->building_id),
    'selectedFloorId' => old('floor_id', $inventory->room?->floor_id),
    'selectedRoomId' => old('room_id', $inventory->room_id),
    'selectedCategoryId' => old('category_id', $inventory->category_id),
    'selectedSubCategoryId' => old('sub_category_id', $inventory->sub_category_id),
])
@endpush
