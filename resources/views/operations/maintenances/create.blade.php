@extends('layouts.app')

@section('title', 'Tambah Maintenance - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('maintenances.index') }}">Maintenance</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="page-header-undira"><h1><i class="fas fa-plus me-2"></i>Tambah Maintenance</h1></div>

<form action="{{ route('maintenances.store') }}" method="POST">
    @csrf
    <div class="card card-undira">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Inventaris <span class="text-danger">*</span></label>
                    <select name="inventory_item_id" class="form-select select2" required>
                        <option value="">Pilih Inventaris</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" {{ old('inventory_item_id', request('inventory_item_id')) == $item->id ? 'selected' : '' }}>{{ $item->inventory_number }} - {{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" class="form-select select2">
                        <option value="">Pilih Vendor</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal Servis <span class="text-danger">*</span></label>
                    <input type="date" name="service_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Jadwal Berikutnya</label>
                    <input type="date" name="next_schedule" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Biaya (Rp)</label>
                    <input type="number" name="cost" class="form-control" min="0" step="0.01">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach(\App\Enums\MaintenanceStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Simpan</button>
            <a href="{{ route('maintenances.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>$('.select2').select2({ theme: 'bootstrap-5', width: '100%' });</script>
@endpush
