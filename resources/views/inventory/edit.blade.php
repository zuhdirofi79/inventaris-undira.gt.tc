@extends('layouts.app')

@section('title', 'Edit Inventaris - Inventaris UNDIRA')

@section('content')
@php
    $editBreadcrumbs = [
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Data Inventaris', 'url' => route('inventory.items')],
    ];
    if ($inventory->category) {
        $editBreadcrumbs[] = ['label' => $inventory->category->name, 'url' => route('inventory.category', $inventory->category)];
    }
    $editBreadcrumbs[] = ['label' => $inventory->inventory_number, 'url' => route('inventory.show', $inventory)];
    $editBreadcrumbs[] = ['label' => 'Edit'];
@endphp
<x-page-header
    title="Edit Inventaris"
    :subtitle="$inventory->inventory_number.' · '.$inventory->name"
    icon="bi bi-pencil-square"
    :breadcrumbs="$editBreadcrumbs"
    :back-route="route('inventory.items')"
    back-label="Data Inventaris"
    :list-back="true"
/>

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
<script>
(function () {
    const $pic = $('#inventory_pic_id');
    if ($pic.length && $.fn.select2) {
        $pic.select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: $pic.data('placeholder') || 'Pilih PIC (Opsional)',
        });
    }
})();
</script>
@endpush
