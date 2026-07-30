@extends('layouts.app')

@section('title', 'Tambah Pemeliharaan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Buat Work Order"
    subtitle="Pilih inventaris (lokasi / nomor / QR / barcode / serial). Ringkasan kondisi, garansi, jadwal, dan riwayat muncul otomatis."
    icon="bi bi-wrench-adjustable"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pemeliharaan Aset', 'url' => route('maintenances.index')],
        ['label' => 'Tambah'],
    ]"
    :back-route="route('maintenances.index')"
    back-label="Pemeliharaan Aset"
    :list-back="true"
>
    <a href="{{ route('maintenances.scan') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-qr-code-scan me-1"></i>Halaman Scan</a>
</x-page-header>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form action="{{ route('maintenances.store') }}" method="POST" enctype="multipart/form-data" id="maintenanceForm">
    @csrf
    <div class="card card-undira mb-3">
        <div class="card-header">
            <strong>1. Pilih Inventaris</strong>
            <div class="small text-muted fw-normal mt-1">
                Metode 1: Kampus → Gedung → Lantai → Ruangan → Kategori → Sub → Barang.
                Metode 2: cari nomor / nama / scan QR / barcode / serial.
            </div>
        </div>
        <div class="card-body">
            <x-inventory-selector
                name="inventory_item_id"
                id="maintenance-inventory-selector"
                :selected-id="$maintenanceContext['id'] ?? null"
                :selected-label="isset($maintenanceContext) ? ($maintenanceContext['inventory_number'].' — '.$maintenanceContext['name']) : null"
                modes="both"
                show-qr-scan
                resolve-maintenance
                help-text="Ketik minimal 2 karakter (nomor, nama, barcode, serial) atau scan QR — inventaris terbuka otomatis."
                label="Inventaris"
            />
        </div>
    </div>

    <x-inventory-maintenance-context :context="$maintenanceContext" />

    <div class="card card-undira">
        <div class="card-header"><strong>2. Detail Work Order</strong></div>
        <div class="card-body">
            <div class="row">
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
                    <input type="file" name="photos_before[]" class="form-control js-maint-photo-input" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple data-preview="previewBefore">
                    <div class="form-text">Maks. 10 foto, 5 MB per file (JPG/PNG/WEBP).</div>
                    <div id="previewBefore" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira btn-lg"><i class="fas fa-save me-1"></i>Simpan Work Order</button>
            <a href="{{ route('maintenances.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/inventory-maintenance-context.js') }}?v={{ @filemtime(public_path('js/inventory-maintenance-context.js')) ?: time() }}"></script>
<script>
(function () {
    $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
    document.querySelectorAll('.js-maint-photo-input').forEach(function (input) {
        input.addEventListener('change', function () {
            var box = document.getElementById(input.getAttribute('data-preview'));
            if (!box) return;
            box.innerHTML = '';
            Array.from(input.files || []).forEach(function (file) {
                if (!file.type || file.type.indexOf('image/') !== 0) return;
                var img = document.createElement('img');
                img.className = 'rounded border';
                img.style.cssText = 'height:72px;width:72px;object-fit:cover;';
                img.alt = file.name;
                img.src = URL.createObjectURL(file);
                box.appendChild(img);
            });
        });
    });
})();
</script>
@endpush
