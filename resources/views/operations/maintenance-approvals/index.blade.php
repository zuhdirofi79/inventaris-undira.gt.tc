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
                        <form action="{{ route('maintenance-approvals.approve', $m) }}" method="POST" class="d-inline" data-no-loading>
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Setujui perbaikan ini? Status inventaris akan Aktif.')">Approve</button>
                        </form>
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

{{-- Modal di luar table-responsive agar submit/backdrop Bootstrap tidak terblokir overflow --}}
<div class="modal fade" id="rejectMaintenanceModal" tabindex="-1" aria-labelledby="rejectMaintenanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form
            method="POST"
            action="{{ old('_reject_url', $maintenances->first() ? route('maintenance-approvals.reject', $maintenances->first()) : '#') }}"
            id="rejectMaintenanceForm"
            class="modal-content"
            data-no-loading
        >
            @csrf
            <input type="hidden" name="_reject_url" id="rejectMaintenanceUrlField" value="{{ old('_reject_url') }}">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectMaintenanceModalLabel">Kembalikan ke Teknisi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
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
                    <div class="invalid-feedback">{{ $message }}</div>
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
@endsection

@push('scripts')
<script>
(function () {
    var modalEl = document.getElementById('rejectMaintenanceModal');
    var form = document.getElementById('rejectMaintenanceForm');
    var urlField = document.getElementById('rejectMaintenanceUrlField');
    var woLabel = document.getElementById('rejectMaintenanceWoLabel');
    var reasonInput = document.getElementById('rejection_reason');
    var submitBtn = document.getElementById('rejectMaintenanceSubmit');

    if (!modalEl || !form) return;

    modalEl.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return;

        var url = trigger.getAttribute('data-reject-url') || '';
        var wo = trigger.getAttribute('data-wo-number') || '—';

        form.setAttribute('action', url);
        if (urlField) urlField.value = url;
        if (woLabel) woLabel.textContent = wo;

        @unless($errors->has('rejection_reason'))
        if (reasonInput) reasonInput.value = '';
        @endunless
    });

    form.addEventListener('submit', function (e) {
        if (!form.getAttribute('action') || form.getAttribute('action') === '#') {
            e.preventDefault();
            alert('Work Order tidak valid.');
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Memproses...';
        }
    });

    @if($errors->has('rejection_reason') && old('_reject_url'))
    document.addEventListener('DOMContentLoaded', function () {
        form.setAttribute('action', @json(old('_reject_url')));
        if (urlField) urlField.value = @json(old('_reject_url'));
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });
    @endif
})();
</script>
@endpush
