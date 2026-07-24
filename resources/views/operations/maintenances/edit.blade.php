@extends('layouts.app')

@section('title', 'Edit Pemeliharaan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Edit Work Order"
    subtitle="{{ $maintenance->maintenance_number }} · {{ $maintenance->inventoryItem?->inventory_number }}"
    icon="bi bi-wrench-adjustable"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pemeliharaan Aset', 'url' => route('maintenances.index')],
        ['label' => 'Edit'],
    ]"
    :back-route="route('maintenances.index')"
    back-label="Pemeliharaan Aset"
    :list-back="true"
/>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form action="{{ route('maintenances.update', $maintenance) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="card card-undira">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Jenis <span class="text-danger">*</span></label>
                    <select name="maintenance_type" class="form-select" required>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected(old('maintenance_type', $maintenance->maintenance_type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Prioritas <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select" required>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', $maintenance->priority?->value) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" id="statusSelect" class="form-select" required>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $maintenance->status?->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3" id="resultWrap">
                    <label class="form-label">Hasil (wajib jika Selesai)</label>
                    <select name="result" class="form-select">
                        <option value="">—</option>
                        @foreach($results as $result)
                            <option value="{{ $result->value }}" @selected(old('result', $maintenance->result?->value) === $result->value)>{{ $result->label() }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Berhasil → Aktif · Tidak dapat diperbaiki · Perlu diganti</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="service_date" class="form-control" value="{{ old('service_date', $maintenance->service_date?->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Teknisi</label>
                    <input type="text" name="technician" class="form-control" value="{{ old('technician', $maintenance->technician) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" class="form-select select2">
                        <option value="">—</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected(old('vendor_id', $maintenance->vendor_id) == $vendor->id)>{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Estimasi Biaya</label>
                    <input type="number" name="estimated_cost" class="form-control" min="0" step="0.01" value="{{ old('estimated_cost', $maintenance->estimated_cost) }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Biaya Aktual</label>
                    <input type="number" name="cost" class="form-control" min="0" step="0.01" value="{{ old('cost', $maintenance->cost) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Jadwal Berikutnya</label>
                    <input type="date" name="next_schedule" class="form-control" value="{{ old('next_schedule', $maintenance->next_schedule?->format('Y-m-d')) }}">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $maintenance->description) }}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Parts Diganti</label>
                    <textarea name="parts_replaced" class="form-control" rows="2">{{ old('parts_replaced', $maintenance->parts_replaced) }}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $maintenance->notes) }}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Foto Saat Perbaikan</label>
                    <input type="file" name="photos_during[]" class="form-control" accept="image/*" multiple>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Foto Sesudah</label>
                    <input type="file" name="photos_after[]" class="form-control" accept="image/*" multiple>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Simpan</button>
            <a href="{{ route('maintenances.show', $maintenance) }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
$('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
</script>
@endpush
