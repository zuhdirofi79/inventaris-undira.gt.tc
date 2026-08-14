@extends('layouts.app')

@section('title', ($maintenance->maintenance_number ?? 'Maintenance').' - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="{{ $maintenance->maintenance_number }}"
    subtitle="{{ $maintenance->inventoryItem?->inventory_number }} · {{ $maintenance->inventoryItem?->name }}"
    icon="bi bi-wrench-adjustable"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pemeliharaan Aset', 'url' => route('maintenances.index')],
        ['label' => $maintenance->maintenance_number],
    ]"
    :back-route="route('maintenances.index')"
    back-label="Pemeliharaan Aset"
>
    @can('maintenance.update')
        @if($maintenance->status?->isOpen())
        <a href="{{ route('maintenances.complete', $maintenance) }}" class="btn btn-undira btn-sm"><i class="bi bi-check2-circle me-1"></i>Selesaikan Perbaikan</a>
        @endif
        <a href="{{ route('maintenances.edit', $maintenance) }}" class="btn btn-outline-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
    @endcan
    @can('maintenance.approve')
        @if($maintenance->status?->value === 'pending_approval')
        <button
            type="button"
            class="btn btn-success btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#approveMaintenanceShowModal"
        >Approve</button>
        <button
            type="button"
            class="btn btn-outline-danger btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#rejectMaintenanceShowModal"
        >Kembalikan</button>
        @endif
    @endcan
</x-page-header>

@if($maintenance->status?->value === 'revision' && filled($maintenance->rejection_reason))
    <div class="alert alert-warning border-0 shadow-sm">
        <div class="fw-semibold mb-1"><i class="bi bi-arrow-counterclockwise me-1"></i>Revisi Perbaikan</div>
        <div>{{ $maintenance->rejection_reason }}</div>
        <div class="small mt-2 mb-0">Perbaiki work order ini, lalu ajukan ulang ke Approval Maintenance melalui tombol <strong>Selesaikan Perbaikan</strong>.</div>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-undira mb-3">
            <div class="card-header"><h5 class="mb-0">Detail Work Order</h5></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge bg-info text-dark">{{ $maintenance->status?->label() }}</span></dd>
                    <dt class="col-sm-4">Jenis</dt><dd class="col-sm-8">{{ $maintenance->maintenance_type?->label() ?? '—' }}</dd>
                    <dt class="col-sm-4">Prioritas</dt><dd class="col-sm-8">{{ $maintenance->priority?->label() ?? '—' }}</dd>
                    <dt class="col-sm-4">Tanggal</dt><dd class="col-sm-8">{{ $maintenance->service_date?->format('d/m/Y') }}</dd>
                    <dt class="col-sm-4">Teknisi</dt><dd class="col-sm-8">{{ $maintenance->technicianUser?->name ?? ($maintenance->technician ?: '—') }}</dd>
                    <dt class="col-sm-4">Jenis Kerusakan</dt><dd class="col-sm-8">{{ $maintenance->damage_type ?: '—' }}</dd>
                    <dt class="col-sm-4">Analisa</dt><dd class="col-sm-8">{{ $maintenance->analysis ?: '—' }}</dd>
                    <dt class="col-sm-4">Pekerjaan</dt><dd class="col-sm-8">{{ $maintenance->work_performed ?: '—' }}</dd>
                    <dt class="col-sm-4">Vendor</dt><dd class="col-sm-8">{{ $maintenance->vendor?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">Estimasi Selesai</dt><dd class="col-sm-8">{{ $maintenance->estimated_completion?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="col-sm-4">Durasi</dt><dd class="col-sm-8">{{ $maintenance->durationHours() !== null ? $maintenance->durationHours().' jam' : '—' }}</dd>
                    <dt class="col-sm-4">Estimasi / Aktual</dt>
                    <dd class="col-sm-8">
                        {{ $maintenance->estimated_cost ? 'Rp '.number_format($maintenance->estimated_cost, 0, ',', '.') : '—' }}
                        /
                        {{ $maintenance->cost ? 'Rp '.number_format($maintenance->cost, 0, ',', '.') : '—' }}
                    </dd>
                    <dt class="col-sm-4">Hasil</dt><dd class="col-sm-8">{{ $maintenance->result?->label() ?? '—' }}</dd>
                    <dt class="col-sm-4">Selesai</dt><dd class="col-sm-8">{{ $maintenance->completed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                    <dt class="col-sm-4">Approval</dt>
                    <dd class="col-sm-8">
                        @if($maintenance->approved_at)
                            Disetujui {{ $maintenance->approver?->name }} · {{ $maintenance->approved_at->format('d/m/Y H:i') }}
                        @elseif($maintenance->rejection_reason)
                            Revisi: {{ $maintenance->rejection_reason }}
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4">Deskripsi</dt><dd class="col-sm-8">{{ $maintenance->description ?: '—' }}</dd>
                    <dt class="col-sm-4">Sparepart</dt><dd class="col-sm-8">{{ $maintenance->parts_replaced ?: '—' }}</dd>
                    <dt class="col-sm-4">Catatan</dt><dd class="col-sm-8">{{ $maintenance->notes ?: '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Foto</h5>
                <span class="small text-muted">Maks. 1 foto · JPG / PNG / WEBP · 5 MB</span>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 small">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @php
                    $woPhotos = $maintenance->photos
                        ->filter(fn ($photo) => filled($photo->path))
                        ->sortBy('id')
                        ->values();
                    $hasPhoto = $woPhotos->isNotEmpty();
                @endphp
                <div class="maint-photo-stage" data-type="before">
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        @can('maintenance.update')
                            @unless($hasPhoto)
                            <form
                                action="{{ route('maintenances.photos.store', $maintenance) }}"
                                method="POST"
                                enctype="multipart/form-data"
                                class="maint-photo-dropzone"
                                data-dropzone
                                data-no-loading
                            >
                                @csrf
                                <input type="hidden" name="type" value="before">
                                <input
                                    type="file"
                                    name="photos[]"
                                    class="d-none"
                                    accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                    data-file-input
                                >
                                <div class="maint-photo-dropzone__inner" data-drop-trigger role="button" tabindex="0" aria-label="Unggah foto work order">
                                    <i class="bi bi-cloud-arrow-up fs-4 d-block mb-1" aria-hidden="true"></i>
                                    <span class="fw-semibold">Drag &amp; drop foto di sini</span>
                                    <span class="small text-muted d-block">atau klik untuk memilih 1 file</span>
                                </div>
                                <div class="maint-photo-preview d-flex flex-wrap gap-2 mt-2" data-preview></div>
                            </form>
                            @endunless
                        @endcan

                        @forelse($woPhotos as $photo)
                            @php $photoUrl = $photo->url; @endphp
                            <div class="maint-photo-thumb position-relative {{ $photoUrl ? '' : 'is-broken' }}">
                                @if($photoUrl)
                                <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="d-block">
                                    <img
                                        src="{{ $photoUrl }}"
                                        alt="Foto work order"
                                        loading="lazy"
                                        onerror="this.classList.add('is-broken'); this.closest('.maint-photo-thumb')?.classList.add('is-broken');"
                                    >
                                </a>
                                @else
                                <div class="maint-photo-thumb__missing small text-muted d-flex align-items-center justify-content-center h-100 px-1 text-center">
                                    File tidak ditemukan
                                </div>
                                @endif
                                @can('maintenance.update')
                                <form
                                    action="{{ route('maintenances.photos.destroy', [$maintenance, $photo]) }}"
                                    method="POST"
                                    class="maint-photo-thumb__delete"
                                    onsubmit="return confirm('Hapus foto ini?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger rounded-circle" title="Hapus foto" aria-label="Hapus foto">
                                        <i class="bi bi-x" aria-hidden="true"></i>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        @empty
                            @cannot('maintenance.update')
                            <span class="text-muted small d-inline-flex align-items-center gap-1 py-3">
                                <i class="bi bi-image" aria-hidden="true"></i> Belum ada foto
                            </span>
                            @endcannot
                        @endforelse
                    </div>
                    @if($hasPhoto)
                        <div class="form-text mt-2">Work order ini sudah memiliki foto. Hapus foto lama jika ingin mengganti.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card card-undira">
            <div class="card-header"><h5 class="mb-0">Riwayat Status</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Waktu</th><th>Dari</th><th>Ke</th><th>Oleh</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @forelse($maintenance->statusHistories as $hist)
                        <tr>
                            <td>{{ $hist->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td>{{ $hist->from_status?->label() ?? '—' }}</td>
                            <td>{{ $hist->to_status?->label() }}</td>
                            <td>{{ $hist->changedBy?->name ?? '—' }}</td>
                            <td>{{ $hist->note ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-undira">
            <div class="card-header"><h5 class="mb-0">Inventaris</h5></div>
            <div class="card-body small">
                <div class="mb-2"><code>{{ $assetCard['inventory_number'] }}</code></div>
                <div class="fw-semibold mb-2">{{ $assetCard['name'] }}</div>
                <div>{{ $assetCard['category'] }} / {{ $assetCard['sub_category'] ?: '—' }}</div>
                <div class="text-muted">{{ collect([$assetCard['campus'], $assetCard['building'], $assetCard['floor'], $assetCard['room']])->filter()->implode(' · ') }}</div>
                <div class="mt-2">PIC: {{ $assetCard['pic'] ?: '—' }}</div>
                <div>Status aset: <strong>{{ $assetCard['status_label'] }}</strong></div>
                @can('inventory.view')
                <a href="{{ route('inventory.show', $maintenance->inventory_item_id) }}" class="btn btn-outline-primary btn-sm mt-3 w-100">Buka Detail Inventaris</a>
                @endcan
            </div>
        </div>
    </div>
</div>

@endsection

@can('maintenance.approve')
@if($maintenance->status?->value === 'pending_approval')
@push('modals')
@include('partials.maintenance-approve-modal', [
    'modalId' => 'approveMaintenanceShowModal',
    'formId' => 'approveMaintenanceShowForm',
    'submitId' => 'approveMaintenanceShowSubmit',
    'action' => route('maintenance-approvals.approve', $maintenance),
    'woNumber' => $maintenance->maintenance_number,
    'itemName' => $maintenance->inventoryItem?->name ?? '—',
    'itemNumber' => $maintenance->inventoryItem?->inventory_number ?? '—',
    'typeLabel' => $maintenance->maintenance_type?->label() ?? '—',
    'priorityLabel' => $maintenance->priority?->label() ?? '—',
])
<div class="modal fade" id="rejectMaintenanceShowModal" tabindex="-1" aria-labelledby="rejectMaintenanceShowModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form
            method="POST"
            action="{{ route('maintenance-approvals.reject', $maintenance) }}"
            id="rejectMaintenanceShowForm"
            class="modal-content"
            data-no-loading
        >
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="rejectMaintenanceShowModalLabel">Kembalikan ke Teknisi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="rejectMaintenanceShowClientError" class="alert alert-danger py-2 small d-none" role="alert"></div>
                <p class="small text-muted mb-2">Work Order: <strong>{{ $maintenance->maintenance_number }}</strong></p>
                <label class="form-label" for="show_rejection_reason">Alasan revisi <span class="text-danger">*</span></label>
                <textarea
                    name="rejection_reason"
                    id="show_rejection_reason"
                    class="form-control @error('rejection_reason') is-invalid @enderror"
                    rows="3"
                    required
                    maxlength="2000"
                >{{ old('rejection_reason') }}</textarea>
                @error('rejection_reason')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">WO akan kembali ke menu Work Order dengan status Revisi Perbaikan.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger" id="rejectMaintenanceShowSubmit">Kembalikan</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
(function () {
    var approveForm = document.getElementById('approveMaintenanceShowForm');
    var approveBtn = document.getElementById('approveMaintenanceShowSubmit');
    var approveModal = document.getElementById('approveMaintenanceShowModal');
    var approveSubmitting = false;

    function cleanupBackdrop() {
        if (document.querySelector('.modal.show')) return;
        document.querySelectorAll('.modal-backdrop').forEach(function (node) {
            if (node.parentNode) node.parentNode.removeChild(node);
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    function resetApproveBtn() {
        approveSubmitting = false;
        if (!approveBtn) return;
        approveBtn.disabled = false;
        approveBtn.classList.remove('is-loading');
        approveBtn.innerHTML = '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>Approve Perbaikan';
    }

    if (approveForm) {
        approveForm.addEventListener('submit', function (e) {
            if (approveSubmitting) {
                e.preventDefault();
                return;
            }
            approveSubmitting = true;
            window.setTimeout(function () {
                if (!approveBtn) return;
                approveBtn.disabled = true;
                approveBtn.classList.add('is-loading');
                approveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...';
            }, 0);
        });
    }

    if (approveModal) {
        approveModal.addEventListener('hidden.bs.modal', function () {
            cleanupBackdrop();
            if (!approveSubmitting) resetApproveBtn();
        });
    }

    var form = document.getElementById('rejectMaintenanceShowForm');
    var reasonInput = document.getElementById('show_rejection_reason');
    var submitBtn = document.getElementById('rejectMaintenanceShowSubmit');
    var clientError = document.getElementById('rejectMaintenanceShowClientError');
    var modalEl = document.getElementById('rejectMaintenanceShowModal');
    var submitting = false;
    if (!form) return;

    function resetSubmitButton() {
        submitting = false;
        if (!submitBtn) return;
        submitBtn.disabled = false;
        submitBtn.textContent = 'Kembalikan';
    }

    function showClientError(message) {
        if (!clientError) { return; }
        clientError.textContent = message;
        clientError.classList.remove('d-none');
    }

    form.addEventListener('submit', function (e) {
        if (clientError) clientError.classList.add('d-none');
        var reason = reasonInput ? String(reasonInput.value || '').trim() : '';
        if (reason.length < 3) {
            e.preventDefault();
            showClientError('Alasan revisi minimal 3 karakter.');
            if (reasonInput) { reasonInput.classList.add('is-invalid'); reasonInput.focus(); }
            resetSubmitButton();
            return;
        }
        if (reasonInput) reasonInput.value = reason;
        if (submitting) { e.preventDefault(); return; }
        submitting = true;
        window.setTimeout(function () {
            if (!submitBtn) return;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Memproses...';
        }, 0);
    });

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            cleanupBackdrop();
            if (!submitting) resetSubmitButton();
        });
    }

    window.addEventListener('pageshow', function () {
        resetApproveBtn();
        resetSubmitButton();
        cleanupBackdrop();
        if (typeof window.hideLoading === 'function') window.hideLoading();
    });

    @if($errors->has('rejection_reason'))
    document.addEventListener('DOMContentLoaded', function () {
        cleanupBackdrop();
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });
    @endif
})();
</script>
@endpush
@endif
@endcan

@push('styles')
<style>
.maint-photo-dropzone {
  width: 200px;
  flex: 0 0 auto;
}
.maint-photo-dropzone__inner {
  border: 2px dashed #2E7D32;
  border-radius: 12px;
  background: rgba(46, 125, 50, 0.04);
  color: #1B5E20;
  text-align: center;
  padding: 1rem 0.75rem;
  min-height: 118px;
  cursor: pointer;
  transition: background .2s ease, border-color .2s ease, box-shadow .2s ease;
}
.maint-photo-dropzone__inner:hover,
.maint-photo-dropzone.is-dragover .maint-photo-dropzone__inner {
  background: rgba(46, 125, 50, 0.1);
  border-color: #1B5E20;
  box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.12);
}
.maint-photo-thumb {
  width: 96px;
  height: 96px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid rgba(15, 23, 42, 0.12);
  background: #f8fafc;
}
.maint-photo-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.maint-photo-thumb.is-broken,
.maint-photo-thumb img.is-broken {
  background: #fee2e2;
}
.maint-photo-thumb__missing {
  font-size: 0.65rem;
  line-height: 1.2;
}
.maint-photo-thumb__delete {
  position: absolute;
  top: 4px;
  right: 4px;
  margin: 0;
}
.maint-photo-thumb__delete .btn {
  width: 24px;
  height: 24px;
  padding: 0;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.maint-photo-add {
  width: 96px;
  height: 96px;
  border: 1px dashed #94a3b8;
  border-radius: 10px;
  background: #fff;
  color: #64748b;
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  cursor: pointer;
  transition: border-color .2s ease, color .2s ease, background .2s ease;
}
.maint-photo-add:hover {
  border-color: #2E7D32;
  color: #2E7D32;
  background: rgba(46, 125, 50, 0.04);
}
.maint-photo-preview img {
  width: 64px;
  height: 64px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid rgba(15, 23, 42, 0.12);
}
body.dark-mode .maint-photo-dropzone__inner {
  background: rgba(46, 125, 50, 0.12);
  color: #a5d6a7;
}
body.dark-mode .maint-photo-thumb,
body.dark-mode .maint-photo-add {
  background: #1f2937;
  border-color: #475569;
  color: #cbd5e1;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    function bindStage(stage) {
        var form = stage.querySelector('[data-dropzone]');
        if (!form) return;
        var input = form.querySelector('[data-file-input]');
        var trigger = form.querySelector('[data-drop-trigger]');
        var preview = form.querySelector('[data-preview]');

        function openPicker() {
            input.click();
        }

        function renderPreview(files) {
            if (!preview) return;
            preview.innerHTML = '';
            var file = files && files[0];
            if (!file || !file.type || file.type.indexOf('image/') !== 0) return;
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            preview.appendChild(img);
        }

        function assignFiles(fileList) {
            if (!fileList || !fileList.length) return;
            var file = Array.from(fileList).find(function (f) {
                return f.type && f.type.indexOf('image/') === 0;
            });
            if (!file) {
                alert('Pilih file gambar (JPG, PNG, atau WEBP).');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran foto maksimal 5 MB.');
                return;
            }
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            renderPreview(input.files);
            form.submit();
        }

        trigger?.addEventListener('click', openPicker);
        trigger?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openPicker();
            }
        });

        input?.addEventListener('change', function () {
            if (input.files && input.files.length) {
                assignFiles(input.files);
            }
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            form.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            form.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.remove('is-dragover');
            });
        });
        form.addEventListener('drop', function (e) {
            assignFiles(e.dataTransfer && e.dataTransfer.files);
        });
    }

    document.querySelectorAll('.maint-photo-stage').forEach(bindStage);
})();
</script>
@endpush
