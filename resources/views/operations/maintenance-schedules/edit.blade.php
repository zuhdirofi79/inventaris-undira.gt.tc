@extends('layouts.app')

@section('title', 'Edit Jadwal Pemeliharaan - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('maintenance-schedules.index') }}">Jadwal Preventif</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<x-ui.page-header title="Edit Jadwal" subtitle="{{ $schedule->inventoryItem?->inventory_number }}" icon="bi bi-calendar2-week" />

<form action="{{ route('maintenance-schedules.update', $schedule) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="card card-undira">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Inventaris</label>
                    <input type="text" class="form-control" value="{{ $schedule->inventoryItem?->inventory_number }} — {{ $schedule->inventoryItem?->name }}" readonly>
                    <input type="hidden" name="inventory_item_id" value="{{ $schedule->inventory_item_id }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Jenis <span class="text-danger">*</span></label>
                    <select name="maintenance_type" class="form-select" required>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected(old('maintenance_type', $schedule->maintenance_type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Interval <span class="text-danger">*</span></label>
                    <input type="number" name="interval_value" class="form-control" min="1" max="3650" value="{{ old('interval_value', $schedule->interval_value) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Satuan <span class="text-danger">*</span></label>
                    <select name="interval_unit" class="form-select" required>
                        @foreach($units as $unit)
                            <option value="{{ $unit->value }}" @selected(old('interval_unit', $schedule->interval_unit?->value) === $unit->value)>{{ $unit->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tanggal Berikutnya <span class="text-danger">*</span></label>
                    <input type="date" name="next_maintenance_date" class="form-control" value="{{ old('next_maintenance_date', $schedule->next_maintenance_date?->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Ingatkan Sebelum <span class="text-danger">*</span></label>
                    <select name="remind_before_days" class="form-select" required>
                        @foreach([7, 14, 30] as $d)
                            <option value="{{ $d }}" @selected((int) old('remind_before_days', $schedule->remind_before_days) === $d)>{{ $d }} hari</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">PIC</label>
                    <input type="text" name="responsible_pic" class="form-control" value="{{ old('responsible_pic', $schedule->responsible_pic) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes', $schedule->notes) }}">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $schedule->is_active))>
                        <label class="form-check-label" for="is_active">Jadwal aktif</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira">Simpan</button>
            <a href="{{ route('maintenance-schedules.show', $schedule) }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection
