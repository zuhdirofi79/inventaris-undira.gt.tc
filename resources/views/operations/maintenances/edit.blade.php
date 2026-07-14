@extends('layouts.app')

@section('title', 'Edit Maintenance - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('maintenances.index') }}">Maintenance</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="page-header-undira"><h1><i class="fas fa-edit me-2"></i>Edit Maintenance</h1></div>

<form action="{{ route('maintenances.update', $maintenance) }}" method="POST">
    @csrf @method('PUT')
    <div class="card card-undira">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" class="form-select select2">
                        <option value="">Pilih Vendor</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}" {{ old('vendor_id', $maintenance->vendor_id) == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach(\App\Enums\MaintenanceStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ old('status', $maintenance->status?->value) == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal Servis</label>
                    <input type="date" name="service_date" class="form-control" value="{{ old('service_date', $maintenance->service_date?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Jadwal Berikutnya</label>
                    <input type="date" name="next_schedule" class="form-control" value="{{ old('next_schedule', $maintenance->next_schedule?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Biaya</label>
                    <input type="number" name="cost" class="form-control" value="{{ old('cost', $maintenance->cost) }}" min="0" step="0.01">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $maintenance->description) }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Perbarui</button>
            <a href="{{ route('maintenances.show', $maintenance) }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>$('.select2').select2({ theme: 'bootstrap-5', width: '100%' });</script>
@endpush
