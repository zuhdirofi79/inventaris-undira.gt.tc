@extends('layouts.app')

@section('title', 'Persetujuan Hapus - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Operasi</li>
    <li class="breadcrumb-item active">Persetujuan Hapus</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-trash-alt me-2"></i>Persetujuan Penghapusan</h1>
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Inventaris</th><th>Diminta Oleh</th><th>Alasan</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($approvals as $approval)
                <tr>
                    <td>
                        {{ $approval->inventoryItem?->inventory_number }}<br>
                        <small>{{ $approval->inventoryItem?->name }}</small>
                    </td>
                    <td>{{ $approval->requestedBy?->name }}</td>
                    <td>{{ Str::limit($approval->reason, 40) }}</td>
                    <td>
                        @php $statusClass = match($approval->status?->value) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }; @endphp
                        <span class="badge bg-{{ $statusClass }}">{{ $approval->status?->label() }}</span>
                    </td>
                    <td>{{ $approval->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($approval->status?->value === 'pending')
                            @can('deletion-approval.approve')
                            <form action="{{ route('deletion-approvals.approve', $approval) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success" title="Setujui"><i class="fas fa-check"></i></button>
                            </form>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $approval->id }}" title="Tolak"><i class="fas fa-times"></i></button>
                            @endcan
                        @else
                            <small class="text-muted">{{ $approval->approvedBy?->name }}</small>
                        @endif
                    </td>
                </tr>

                @can('deletion-approval.approve')
                @if($approval->status?->value === 'pending')
                <div class="modal fade" id="rejectModal{{ $approval->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <form action="{{ route('deletion-approvals.reject', $approval) }}" method="POST" class="modal-content">
                            @csrf
                            <div class="modal-header"><h5 class="modal-title">Tolak Penghapusan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <label class="form-label">Alasan Penolakan</label>
                                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="modal-footer"><button type="submit" class="btn btn-danger">Tolak</button></div>
                        </form>
                    </div>
                </div>
                @endif
                @endcan
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada permintaan penghapusan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($approvals->hasPages())<div class="card-footer">{{ $approvals->links() }}</div>@endif
</div>
@endsection
