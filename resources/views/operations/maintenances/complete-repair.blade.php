@extends('layouts.app')

@section('title', 'Selesaikan Perbaikan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Selesaikan Perbaikan"
    subtitle="Setelah disimpan, status inventaris menjadi Menunggu Approval Admin (bukan Aktif)."
    icon="bi bi-check2-circle"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Work Order', 'url' => route('maintenances.index')],
        ['label' => $maintenance->maintenance_number, 'url' => route('maintenances.show', $maintenance)],
        ['label' => 'Selesaikan'],
    ]"
    :back-route="route('maintenances.show', $maintenance)"
    back-label="Detail Work Order"
/>

<div class="alert alert-warning border-0 shadow-sm">
    Inventaris <strong>{{ $maintenance->inventoryItem?->inventory_number }}</strong> belum akan Aktif.
    Admin harus menyetujui terlebih dahulu.
</div>

<form action="{{ route('maintenances.complete.store', $maintenance) }}" method="POST" enctype="multipart/form-data" data-page-shortcut-save>
    @csrf
    <div class="card card-undira mb-3">
        <div class="card-header"><h5 class="mb-0">Form Penyelesaian</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                    <input type="date" name="completed_at" class="form-control" value="{{ old('completed_at', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Biaya</label>
                    <input type="number" step="0.01" min="0" name="cost" class="form-control" value="{{ old('cost', $maintenance->cost) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Pekerjaan <span class="text-danger">*</span></label>
                    <textarea name="work_performed" class="form-control" rows="4" required>{{ old('work_performed', $maintenance->work_performed) }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $maintenance->notes) }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Upload Foto</label>
                    @if($maintenance->photos->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach($maintenance->photos->filter(fn ($p) => filled($p->url)) as $photo)
                                <img src="{{ $photo->url }}" alt="Foto work order" class="rounded border" style="height:72px;width:72px;object-fit:cover;" loading="lazy">
                            @endforeach
                        </div>
                        <div class="form-text">Work order sudah memiliki foto. Ganti dari halaman detail jika diperlukan.</div>
                    @else
                        <input type="file" name="photos_after[]" class="form-control js-maint-photo-input" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-preview="previewAfter">
                        <div class="form-text">Opsional · 1 foto · JPG/PNG/WEBP · maks. 5 MB</div>
                        <div id="previewAfter" class="d-flex flex-wrap gap-2 mt-2"></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-undira"><i class="bi bi-send me-1"></i>Ajukan Approval</button>
            <a href="{{ route('maintenances.show', $maintenance) }}" class="btn btn-outline-secondary">Batal</a>
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
