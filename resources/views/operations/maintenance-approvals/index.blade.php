@extends('layouts.app')

@section('title', 'Approval Maintenance - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Approval Maintenance"
    subtitle="Setujui perbaikan teknisi atau kembalikan untuk revisi."
    icon="bi bi-clipboard-check"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Approval Maintenance'],
    ]"
    :back-route="route('maintenance-dashboard.index')"
    back-label="Dashboard Maintenance"
/>

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Tidak dapat mengembalikan work order.</div>
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card card-undira mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small">Cari</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="No WO / inventaris / teknisi">
            </div>
            <div class="col-md-3">
                <button class="btn btn-undira"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card card-undira">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>WO</th>
                    <th>Inventaris</th>
                    <th>Teknisi</th>
                    <th>Diajukan</th>
                    <th>Biaya</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($maintenances as $m)
                <tr>
                    <td><a href="{{ route('maintenances.show', $m) }}"><code>{{ $m->maintenance_number }}</code></a></td>
                    <td>
                        <div class="fw-semibold">{{ $m->inventoryItem?->name }}</div>
                        <small class="text-muted">{{ $m->inventoryItem?->inventory_number }}</small>
                    </td>
                    <td>{{ $m->technicianUser?->name ?? $m->technician ?? '—' }}</td>
                    <td>{{ $m->submitted_for_approval_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $m->cost !== null ? 'Rp '.number_format((float) $m->cost, 0, ',', '.') : '—' }}</td>
                    <td class="text-nowrap">
                        <button
                            type="button"
                            class="btn btn-sm btn-success"
                            data-bs-toggle="modal"
                            data-bs-target="#approveMaintenanceModal"
                            data-approve-url="{{ route('maintenance-approvals.approve', $m) }}"
                            data-wo-number="{{ $m->maintenance_number }}"
                            data-item-name="{{ $m->inventoryItem?->name ?? '—' }}"
                            data-item-number="{{ $m->inventoryItem?->inventory_number ?? '—' }}"
                            data-type-label="{{ $m->maintenance_type?->label() ?? '—' }}"
                            data-priority-label="{{ $m->priority?->label() ?? '—' }}"
                        >Approve</button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#rejectMaintenanceModal"
                            data-reject-url="{{ route('maintenance-approvals.reject', $m) }}"
                            data-wo-number="{{ $m->maintenance_number }}"
                        >Kembalikan</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada maintenance menunggu approval.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($maintenances->hasPages())
        <div class="card-footer">{{ $maintenances->links() }}</div>
    @endif
</div>
@endsection

{{-- MUST be outside .content-wrapper (see undira-modals.css + layouts/app.blade.php @stack('modals')) --}}
@push('modals')
@include('partials.maintenance-approve-modal', [
    'action' => $maintenances->first() ? route('maintenance-approvals.approve', $maintenances->first()) : '#',
])
<div class="modal fade" id="rejectMaintenanceModal" tabindex="-1" aria-labelledby="rejectMaintenanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form
            method="POST"
            action="{{ old('_reject_url', $maintenances->first() ? route('maintenance-approvals.reject', $maintenances->first()) : '#') }}"
            id="rejectMaintenanceForm"
            class="modal-content"
            data-no-loading
            novalidate
        >
            @csrf
            <input type="hidden" name="_reject_url" id="rejectMaintenanceUrlField" value="{{ old('_reject_url') }}">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectMaintenanceModalLabel">Kembalikan ke Teknisi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="rejectMaintenanceClientError" class="alert alert-danger py-2 small d-none" role="alert"></div>
                <p class="small text-muted mb-2">
                    Work Order: <strong id="rejectMaintenanceWoLabel">—</strong>
                </p>
                <label class="form-label" for="rejection_reason">Alasan revisi <span class="text-danger">*</span></label>
                <textarea
                    name="rejection_reason"
                    id="rejection_reason"
                    class="form-control @error('rejection_reason') is-invalid @enderror"
                    rows="3"
                    required
                    maxlength="2000"
                    placeholder="Contoh: Foto sesudah kurang jelas / sparepart belum diganti"
                >{{ old('rejection_reason') }}</textarea>
                @error('rejection_reason')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">WO akan kembali ke menu Work Order dengan status Revisi Perbaikan.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger" id="rejectMaintenanceSubmit">Kembalikan</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
(function () {
    // —— Approve confirmation modal ——
    (function () {
        var modalEl = document.getElementById('approveMaintenanceModal');
        var form = document.getElementById('approveMaintenanceForm');
        var submitBtn = document.getElementById('approveMaintenanceSubmit');
        var errorEl = modalEl ? modalEl.querySelector('[data-approve-error]') : null;
        var submitting = false;
        if (!modalEl || !form) return;

        function setText(sel, value) {
            var el = modalEl.querySelector(sel);
            if (el) el.textContent = value || '—';
        }

        function resetSubmit() {
            submitting = false;
            if (!submitBtn) return;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>Approve Perbaikan';
            submitBtn.classList.remove('is-loading');
        }

        function cleanupBackdrop() {
            if (document.querySelector('.modal.show')) return;
            document.querySelectorAll('.modal-backdrop').forEach(function (node) {
                if (node.parentNode) node.parentNode.removeChild(node);
            });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        modalEl.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) return;
            var url = trigger.getAttribute('data-approve-url') || '';
            form.setAttribute('action', url);
            setText('[data-approve-wo]', trigger.getAttribute('data-wo-number'));
            setText('[data-approve-item-name]', trigger.getAttribute('data-item-name'));
            setText('[data-approve-item-number]', trigger.getAttribute('data-item-number'));
            setText('[data-approve-type]', trigger.getAttribute('data-type-label'));
            setText('[data-approve-priority]', trigger.getAttribute('data-priority-label'));
            if (errorEl) {
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
            }
            resetSubmit();
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            cleanupBackdrop();
            if (!submitting) resetSubmit();
        });

        form.addEventListener('submit', function (e) {
            var action = form.getAttribute('action') || '';
            if (!action || action === '#' || action.indexOf('/maintenance-approvals/') === -1) {
                e.preventDefault();
                if (errorEl) {
                    errorEl.textContent = 'Work Order tidak valid. Tutup modal lalu klik Approve lagi.';
                    errorEl.classList.remove('d-none');
                }
                resetSubmit();
                return;
            }
            if (submitting) {
                e.preventDefault();
                return;
            }
            submitting = true;
            window.setTimeout(function () {
                if (!submitBtn) return;
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...';
            }, 0);
        });

        window.addEventListener('pageshow', function () {
            resetSubmit();
            cleanupBackdrop();
        });
    })();

    // —— Reject modal (existing) ——
(function () {
    var modalEl = document.getElementById('rejectMaintenanceModal');
    var form = document.getElementById('rejectMaintenanceForm');
    var urlField = document.getElementById('rejectMaintenanceUrlField');
    var woLabel = document.getElementById('rejectMaintenanceWoLabel');
    var reasonInput = document.getElementById('rejection_reason');
    var submitBtn = document.getElementById('rejectMaintenanceSubmit');
    var clientError = document.getElementById('rejectMaintenanceClientError');
    var submitting = false;

    if (!modalEl || !form) return;

    function cleanupBackdrop() {
        if (document.querySelector('.modal.show')) return;
        document.querySelectorAll('.modal-backdrop').forEach(function (node) {
            if (node.parentNode) node.parentNode.removeChild(node);
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    function resetSubmitButton() {
        submitting = false;
        if (!submitBtn) return;
        submitBtn.disabled = false;
        submitBtn.textContent = 'Kembalikan';
        submitBtn.classList.remove('is-loading', 'btn-undira-loading');
        submitBtn.removeAttribute('aria-busy');
    }

    function showClientError(message) {
        if (!clientError) {
            window.alert(message);
            return;
        }
        clientError.textContent = message;
        clientError.classList.remove('d-none');
    }

    function hideClientError() {
        if (!clientError) return;
        clientError.textContent = '';
        clientError.classList.add('d-none');
    }

    modalEl.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (trigger) {
            var url = trigger.getAttribute('data-reject-url') || '';
            var wo = trigger.getAttribute('data-wo-number') || '—';
            form.setAttribute('action', url);
            if (urlField) urlField.value = url;
            if (woLabel) woLabel.textContent = wo;
        }

        @unless($errors->has('rejection_reason'))
        if (reasonInput && !submitting) reasonInput.value = '';
        @endunless

        hideClientError();
        resetSubmitButton();
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        cleanupBackdrop();
        if (!submitting) resetSubmitButton();
    });

    form.addEventListener('submit', function (e) {
        hideClientError();

        var action = form.getAttribute('action') || '';
        if (!action || action === '#' || action.indexOf('/maintenance-approvals/') === -1) {
            e.preventDefault();
            showClientError('Work Order tidak valid. Tutup modal lalu klik Kembalikan lagi.');
            resetSubmitButton();
            return;
        }

        var reason = reasonInput ? String(reasonInput.value || '').trim() : '';
        if (reason.length < 3) {
            e.preventDefault();
            showClientError('Alasan revisi minimal 3 karakter.');
            if (reasonInput) {
                reasonInput.classList.add('is-invalid');
                reasonInput.focus();
            }
            resetSubmitButton();
            return;
        }

        if (reasonInput) reasonInput.value = reason;
        if (submitting) {
            e.preventDefault();
            return;
        }
        submitting = true;

        window.setTimeout(function () {
            if (!submitBtn) return;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Memproses...';
            submitBtn.setAttribute('aria-busy', 'true');
        }, 0);
    });

    window.addEventListener('pageshow', function () {
        resetSubmitButton();
        cleanupBackdrop();
        if (typeof window.hideLoading === 'function') window.hideLoading();
    });

    @if($errors->has('rejection_reason') && old('_reject_url'))
    document.addEventListener('DOMContentLoaded', function () {
        form.setAttribute('action', @json(old('_reject_url')));
        if (urlField) urlField.value = @json(old('_reject_url'));
        cleanupBackdrop();
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });
    @endif
})();
})();
</script>
@endpush
