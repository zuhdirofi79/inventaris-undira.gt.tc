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
                        <form action="{{ route('maintenance-approvals.approve', $m) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-success" onclick="return confirm('Setujui perbaikan ini? Status inventaris akan Aktif.')">Approve</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $m->id }}">Kembalikan</button>

                        <div class="modal fade" id="rejectModal{{ $m->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form action="{{ route('maintenance-approvals.reject', $m) }}" method="POST" class="modal-content">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">Kembalikan ke Teknisi</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <label class="form-label">Alasan revisi <span class="text-danger">*</span></label>
                                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-danger">Kembalikan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
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
