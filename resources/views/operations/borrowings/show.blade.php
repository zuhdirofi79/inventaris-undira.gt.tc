@extends('layouts.app')

@section('title', 'Detail Peminjaman - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Detail Peminjaman"
    subtitle="{{ $borrowing->borrower_name }} · {{ $borrowing->inventoryItem?->inventory_number }}"
    icon="bi bi-hand-index"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Peminjaman', 'url' => route('borrowings.index')],
        ['label' => 'Detail'],
    ]"
    :back-route="route('borrowings.index')"
    back-label="Daftar Peminjaman"
>
    @can('borrowing.update')
    <a href="{{ route('borrowings.edit', $borrowing) }}" class="btn btn-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
    @endcan
    @if($borrowing->approval_number)
    <a href="{{ route('borrowings.receipt', $borrowing) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-receipt me-1"></i>Receipt</a>
    <a href="{{ route('borrowings.receipt.pdf', $borrowing) }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-file-pdf me-1"></i>PDF</a>
    @endif
    @can('approve', $borrowing)
    <form action="{{ route('borrowings.approve', $borrowing) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Approve</button>
    </form>
    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectBorrowingModal">
        <i class="fas fa-times me-1"></i>Reject
    </button>
    @endcan
</x-page-header>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Data Peminjaman</h5></div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Nomor Approval</th><td>{{ $borrowing->approval_number ?? '—' }}</td></tr>
                    <tr><th>Peminjam</th><td>{{ $borrowing->borrower_name }}</td></tr>
                    <tr><th>Email</th><td>{{ $borrowing->borrower_email ?? '-' }}</td></tr>
                    <tr><th>Telepon</th><td>{{ $borrowing->borrower_phone ?? '-' }}</td></tr>
                    <tr><th>Tgl Pinjam</th><td>{{ $borrowing->borrow_date?->format('d/m/Y') }}</td></tr>
                    <tr><th>Tgl Kembali (Rencana)</th><td>{{ $borrowing->expected_return_date?->format('d/m/Y') ?? '-' }}</td></tr>
                    <tr><th>Tgl Kembali (Aktual)</th><td>{{ $borrowing->return_date?->format('d/m/Y') ?? '-' }}</td></tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-{{ $borrowing->status?->badgeColor() ?? 'secondary' }}">{{ $borrowing->status?->label() }}</span>
                        </td>
                    </tr>
                    <tr><th>Status Approval</th><td>{{ $borrowing->approvalStatusLabel() }}</td></tr>
                    <tr><th>Diajukan Oleh</th><td>{{ $borrowing->requestedBy?->name ?? '-' }}</td></tr>
                    <tr><th>Disetujui / Diproses Oleh</th><td>{{ $borrowing->approvedBy?->name ?? '-' }}</td></tr>
                    <tr><th>Tanggal Persetujuan</th><td>{{ $borrowing->approved_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
                    <tr><th>Tanggal Penolakan</th><td>{{ $borrowing->rejected_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
                    <tr><th>Alasan Penolakan</th><td>{{ $borrowing->rejection_reason ?? '-' }}</td></tr>
                    <tr><th>Catatan Approval</th><td>{{ $borrowing->approval_notes ?? '-' }}</td></tr>
                    <tr><th>Catatan</th><td>{{ $borrowing->notes ?? '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Inventaris</h5></div>
            <div class="card-body">
                @if($borrowing->inventoryItem)
                <p><strong>{{ $borrowing->inventoryItem->name }}</strong></p>
                <p><code>{{ $borrowing->inventoryItem->inventory_number }}</code></p>
                <p class="text-muted small">{{ $borrowing->inventoryItem->full_location }}</p>
                <p class="small mb-1"><strong>Kategori:</strong> {{ $borrowing->inventoryItem->category?->name ?? '—' }}</p>
                <p class="small mb-1"><strong>Subkategori:</strong> {{ $borrowing->inventoryItem->subCategory?->name ?? '—' }}</p>
                <p class="small mb-3"><strong>PIC:</strong> {{ $borrowing->inventoryItem->picDisplayName() ?: '—' }}</p>
                <a href="{{ route('inventory.show', $borrowing->inventoryItem) }}" class="btn btn-outline-primary btn-sm">Lihat Inventaris</a>
                @else
                <p class="text-muted">Inventaris tidak ditemukan.</p>
                @endif
            </div>
        </div>

        @if($borrowing->approval_number)
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">QR Code Approval</h5></div>
            <div class="card-body text-center">
                @if(!empty($qrDataUri))
                    <img src="{{ $qrDataUri }}" alt="QR Approval" style="width:160px;height:160px">
                @endif
                <div class="mt-2">
                    <a href="{{ $approvalUrl }}" target="_blank" rel="noopener">{{ $borrowing->approval_number }}</a>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@can('approve', $borrowing)
<div class="modal fade" id="rejectBorrowingModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('borrowings.reject', $borrowing) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Tolak Peminjaman</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection
