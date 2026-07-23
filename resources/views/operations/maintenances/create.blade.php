@extends('layouts.app')

@section('title', 'Tambah Pemeliharaan - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('maintenances.index') }}">Maintenance</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<x-ui.page-header title="Form Pemeliharaan" subtitle="Inventaris akan berstatus Dalam Pemeliharaan (tidak dapat dipinjam)" icon="bi bi-wrench-adjustable">
    <a href="{{ route('maintenances.scan') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-qr-code-scan me-1"></i>Scan QR</a>
</x-ui.page-header>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($assetCard)
<div class="card card-undira mb-3">
    <div class="card-header"><h5 class="mb-0">Identitas Inventaris</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2 text-center">
                @if(!empty($assetCard['photo_url']))
                    <img src="{{ $assetCard['photo_url'] }}" alt="" class="img-fluid rounded border" style="max-height:120px;">
                @else
                    <div class="text-muted small py-4">Tanpa foto</div>
                @endif
            </div>
            <div class="col-md-10">
                <dl class="row mb-0 small">
                    <dt class="col-sm-3">No. Inventaris</dt><dd class="col-sm-9"><code>{{ $assetCard['inventory_number'] }}</code></dd>
                    <dt class="col-sm-3">Nama</dt><dd class="col-sm-9">{{ $assetCard['name'] }}</dd>
                    <dt class="col-sm-3">Kategori / Sub</dt><dd class="col-sm-9">{{ $assetCard['category'] }} / {{ $assetCard['sub_category'] ?: '—' }}</dd>
                    <dt class="col-sm-3">Lokasi</dt><dd class="col-sm-9">{{ collect([$assetCard['campus'], $assetCard['building'], $assetCard['floor'], $assetCard['room']])->filter()->implode(' / ') }}</dd>
                    <dt class="col-sm-3">PIC</dt><dd class="col-sm-9">{{ $assetCard['pic'] ?: '—' }}</dd>
                    <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ $assetCard['status_label'] }}</dd>
                    <dt class="col-sm-3">Tahun / Pengadaan</dt><dd class="col-sm-9">{{ $assetCard['purchase_year'] ?: '—' }} · {{ $assetCard['procurement_type'] ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endif

<form action="{{ route('maintenances.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="card card-undira">
        <div class="card-body">
            <div class="row">
                @unless($selectedItem)
                <div class="col-md-6 mb-3">
                    <label class="form-label">Inventaris <span class="text-danger">*</span></label>
                    <select name="inventory_item_id" class="form-select select2-inventory" required
                            data-lookup-url="{{ route('inventory.lookup') }}"
                            data-placeholder="Ketik nomor / nama inventaris…">
                        <option value="">Pilih Inventaris</option>
                    </select>
                    <div class="form-text">Lebih cepat: gunakan Scan QR.</div>
                </div>
                @else
                    <input type="hidden" name="inventory_item_id" value="{{ $selectedItem->id }}">
                @endunless

                <div class="col-md-6 mb-3">
                    <label class="form-label">Jenis Pemeliharaan <span class="text-danger">*</span></label>
                    <select name="maintenance_type" class="form-select" required>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected(old('maintenance_type', 'repair') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="service_date" class="form-control" value="{{ old('service_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Prioritas <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select" required>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status awal</label>
                    <select name="status" class="form-select">
                        @foreach($statuses as $status)
                            @if($status->isOpen())
                                <option value="{{ $status->value }}" @selected(old('status', 'waiting') === $status->value)>{{ $status->label() }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teknisi</label>
                    <input type="text" name="technician" class="form-control" value="{{ old('technician') }}" maxlength="255" placeholder="Nama teknisi">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" class="form-select select2">
                        <option value="">—</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Estimasi Biaya (Rp)</label>
                    <input type="number" name="estimated_cost" class="form-control" min="0" step="0.01" value="{{ old('estimated_cost') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Biaya Aktual (Rp)</label>
                    <input type="number" name="cost" class="form-control" min="0" step="0.01" value="{{ old('cost') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Jadwal Berikutnya</label>
                    <input type="date" name="next_schedule" class="form-control" value="{{ old('next_schedule') }}">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Parts Diganti</label>
                    <textarea name="parts_replaced" class="form-control" rows="2">{{ old('parts_replaced') }}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Foto Sebelum Perbaikan</label>
                    <input type="file" name="photos_before[]" class="form-control" accept="image/*" multiple>
                    <div class="form-text">Maks. 10 foto, 5 MB per file.</div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira btn-lg"><i class="fas fa-save me-1"></i>Simpan Pemeliharaan</button>
            <a href="{{ route('maintenances.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const $inv = $('.select2-inventory');
    if ($inv.length) {
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
    }
    $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
})();
</script>
@endpush
