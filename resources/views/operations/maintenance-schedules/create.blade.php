@extends('layouts.app')

@section('title', 'Tambah Jadwal Pemeliharaan - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('maintenance-schedules.index') }}">Jadwal Preventif</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<x-ui.page-header title="Buat Jadwal Preventif" subtitle="Contoh: AC tiap 3 bulan, generator tiap 6 bulan, APAR tiap tahun" icon="bi bi-calendar2-plus" />

<form action="{{ route('maintenance-schedules.store') }}" method="POST">
    @csrf
    <div class="card card-undira">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Inventaris <span class="text-danger">*</span></label>
                    <select name="inventory_item_id" class="form-select select2-inventory" required
                            data-lookup-url="{{ route('inventory.lookup') }}"
                            data-placeholder="Ketik nomor / nama inventaris…">
                        <option value="">Pilih Inventaris</option>
                        @if($selectedItem)
                            <option value="{{ $selectedItem->id }}" selected>{{ $selectedItem->inventory_number }} - {{ $selectedItem->name }}</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Jenis Pemeliharaan <span class="text-danger">*</span></label>
                    <select name="maintenance_type" class="form-select" required>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected(old('maintenance_type', 'preventive') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Interval <span class="text-danger">*</span></label>
                    <input type="number" name="interval_value" class="form-control" min="1" max="3650" value="{{ old('interval_value', 3) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Satuan <span class="text-danger">*</span></label>
                    <select name="interval_unit" class="form-select" required>
                        @foreach($units as $unit)
                            <option value="{{ $unit->value }}" @selected(old('interval_unit', 'months') === $unit->value)>{{ $unit->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tanggal Berikutnya <span class="text-danger">*</span></label>
                    <input type="date" name="next_maintenance_date" class="form-control" value="{{ old('next_maintenance_date', now()->addMonth()->toDateString()) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Ingatkan Sebelum <span class="text-danger">*</span></label>
                    <select name="remind_before_days" class="form-select" required>
                        @foreach([7, 14, 30] as $d)
                            <option value="{{ $d }}" @selected((int) old('remind_before_days', 7) === $d)>{{ $d }} hari</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">PIC Penanggung Jawab</label>
                    <input type="text" name="responsible_pic" class="form-control" value="{{ old('responsible_pic', $selectedItem?->responsible_person) }}" maxlength="255">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', true))>
                        <label class="form-check-label" for="is_active">Jadwal aktif</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Simpan Jadwal</button>
            <a href="{{ route('maintenance-schedules.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const $inv = $('.select2-inventory');
    $inv.select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: $inv.data('placeholder'),
        minimumInputLength: 1,
        ajax: {
            url: $inv.data('lookup-url'),
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return data; },
        },
    });
})();
</script>
@endpush
