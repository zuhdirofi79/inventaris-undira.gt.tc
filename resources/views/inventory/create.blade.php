@extends('layouts.app')

@section('title', 'Tambah Inventaris - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-plus me-2"></i>Tambah Inventaris</h1>
    <p class="text-muted mb-0 small">Nomor inventaris, QR, dan barcode dibuat otomatis saat simpan.</p>
</div>

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
