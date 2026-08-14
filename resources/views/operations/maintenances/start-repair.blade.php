@extends('layouts.app')

@section('title', 'Mulai Perbaikan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Mulai Perbaikan"
    subtitle="Status inventaris akan menjadi Dalam Perbaikan setelah disimpan."
    icon="bi bi-tools"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Work Order', 'url' => route('maintenances.index')],
        ['label' => 'Mulai Perbaikan'],
    ]"
    :back-route="route('maintenances.scan')"
    back-label="Scan QR"
    :list-back="true"
/>

<div class="card card-undira mb-3">
    <div class="card-body">
        <div class="row g-2 small">
            <div class="col-md-4"><strong>No. Inventaris:</strong> <code>{{ $item->inventory_number }}</code></div>
            <div class="col-md-4"><strong>Nama:</strong> {{ $item->name }}</div>
            <div class="col-md-4"><strong>Lokasi:</strong> {{ $item->full_location }}</div>
            <div class="col-md-4"><strong>Kategori:</strong> {{ $item->category?->name }} / {{ $item->subCategory?->name }}</div>
            <div class="col-md-4"><strong>PIC:</strong> {{ $item->picDisplayName() ?? 'Belum ditentukan' }}</div>
            <div class="col-md-4"><strong>Status saat ini:</strong> {{ $item->status?->label() }}</div>
        </div>
    </div>
</div>

<form action="{{ route('maintenances.start-repair.store') }}" method="POST" enctype="multipart/form-data" data-page-shortcut-save>
    @csrf
    <input type="hidden" name="inventory_item_id" value="{{ $item->id }}">

    <div class="card card-undira mb-3">
        <div class="card-header"><h5 class="mb-0">Form Perbaikan</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="service_date" class="form-control" value="{{ old('service_date', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Teknisi</label>
                    <select name="technician_user_id" class="form-select">
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}" @selected(old('technician_user_id', auth()->id()) == $tech->id)>{{ $tech->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estimasi Selesai</label>
                    <input type="date" name="estimated_completion" class="form-control" value="{{ old('estimated_completion') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Jenis Kerusakan <span class="text-danger">*</span></label>
                    <input type="text" name="damage_type" class="form-control" value="{{ old('damage_type') }}" required placeholder="Contoh: Tidak menyala / Layar rusak">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sparepart</label>
                    <input type="text" name="parts_replaced" class="form-control" value="{{ old('parts_replaced') }}" placeholder="Opsional">
                </div>
                <div class="col-12">
                    <label class="form-label">Analisa <span class="text-danger">*</span></label>
                    <textarea name="analysis" class="form-control" rows="3" required>{{ old('analysis') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Pekerjaan</label>
                    <textarea name="work_performed" class="form-control" rows="3">{{ old('work_performed') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Upload Foto</label>
                    <input type="file" name="photos_before[]" class="form-control js-maint-photo-input" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-preview="previewBefore">
                    <div class="form-text">Opsional · 1 foto · JPG/PNG/WEBP · maks. 5 MB</div>
                    <div id="previewBefore" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-undira"><i class="bi bi-play-fill me-1"></i>Mulai Perbaikan</button>
            <a href="{{ route('maintenances.scan') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
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
</script>
@endpush
