@extends('layouts.app')

@section('title', 'Tambah Inventaris - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Tambah Inventaris"
    subtitle="Nomor inventaris, QR, dan barcode dibuat otomatis saat simpan."
    icon="bi bi-plus-lg"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Data Inventaris', 'url' => route('inventory.index')],
        ['label' => 'Tambah'],
    ]"
    :back-route="route('inventory.index')"
    back-label="Data Inventaris"
    :list-back="true"
/>

<form action="{{ route('inventory.store') }}" method="POST" class="inventory-form">
    @csrf
    @include('inventory._form')
    <div class="d-flex flex-wrap gap-2 sticky-form-actions">
        <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Simpan</button>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection

@push('scripts')
@include('inventory._location-cascade-script', [
    'selectedCampusId' => old('campus_id', request('campus_id')),
    'selectedBuildingId' => old('building_id', request('building_id')),
    'selectedFloorId' => old('floor_id', request('floor_id')),
    'selectedRoomId' => old('room_id', request('room_id')),
    'selectedCategoryId' => old('category_id', request('category_id')),
    'selectedSubCategoryId' => old('sub_category_id'),
])
@endpush
