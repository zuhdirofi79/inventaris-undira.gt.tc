@extends('layouts.app')

@section('title', 'Detail Peminjaman - Inventaris UNDIRA')

@php
    use App\Enums\BorrowingItemStatus;
    use App\Enums\BorrowingStatus;
    $lines = $borrowing->items->isNotEmpty() ? $borrowing->items : collect();
    $itemCount = $lines->count() ?: ($borrowing->inventoryItem ? 1 : 0);
@endphp

@section('content')
<x-page-header
    title="Detail Peminjaman"
    subtitle="{{ $borrowing->borrower_name }} · {{ $itemCount }} item{{ $borrowing->approval_number ? ' · '.$borrowing->approval_number : '' }}"
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
    <div class="col-lg-5">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Data Peminjam</h5></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
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
                    <tr><th>Jumlah Item</th><td>{{ $itemCount }}</td></tr>
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

    <div class="col-lg-7">
        <div class="card card-undira mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Daftar Barang</h5>
            </div>
            <div class="card-body p-0">
                @php
                    $canReturn = auth()->user()?->can('update', $borrowing)
                        && in_array($borrowing->status, [BorrowingStatus::Disetujui, BorrowingStatus::Dipinjam, BorrowingStatus::Terlambat], true);
                    $openLines = $lines->filter(fn ($l) => $l->status === BorrowingItemStatus::Dipinjam);
                @endphp

                @if($canReturn && $openLines->isNotEmpty())
                <form action="{{ route('borrowings.return-items', $borrowing) }}" method="POST" id="returnItemsForm">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:36px"></th>
                                    <th>Nomor Inventaris</th>
                                    <th>Nama</th>
                                    <th>Kategori</th>
                                    <th>Sub Kategori</th>
                                    <th>Lokasi</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $line)
                                    @php $inv = $line->inventoryItem; @endphp
                                    <tr>
                                        <td>
                                            @if($line->status === BorrowingItemStatus::Dipinjam)
                                                <input type="checkbox" name="item_ids[]" value="{{ $line->id }}" class="form-check-input">
                                            @endif
                                        </td>
                                        <td>
                                            @if($inv)
                                                <a href="{{ route('inventory.show', $inv) }}"><code>{{ $inv->inventory_number }}</code></a>
                                            @else —
                                            @endif
                                        </td>
                                        <td>{{ $inv?->name ?? '—' }}</td>
                                        <td>{{ $inv?->category?->name ?? '—' }}</td>
                                        <td>{{ $inv?->subCategory?->name ?? '—' }}</td>
                                        <td class="small">{{ $inv?->full_location ?? '—' }}</td>
                                        <td><span class="badge bg-{{ $line->status?->badgeColor() ?? 'secondary' }}">{{ $line->status?->label() ?? '—' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-sm btn-outline-primary" name="return_all" value="0">
                            <i class="fas fa-undo me-1"></i>Kembalikan Terpilih
                        </button>
                        <button type="submit" class="btn btn-sm btn-undira" name="return_all" value="1">
                            <i class="fas fa-check-double me-1"></i>Kembalikan Semua
                        </button>
                    </div>
                </form>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nomor Inventaris</th>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Sub Kategori</th>
                                <th>Lokasi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $line)
                                @php $inv = $line->inventoryItem; @endphp
                                <tr>
                                    <td>
                                        @if($inv)
                                            <a href="{{ route('inventory.show', $inv) }}"><code>{{ $inv->inventory_number }}</code></a>
                                        @else —
                                        @endif
                                    </td>
                                    <td>{{ $inv?->name ?? '—' }}</td>
                                    <td>{{ $inv?->category?->name ?? '—' }}</td>
                                    <td>{{ $inv?->subCategory?->name ?? '—' }}</td>
                                    <td class="small">{{ $inv?->full_location ?? '—' }}</td>
                                    <td><span class="badge bg-{{ $line->status?->badgeColor() ?? 'secondary' }}">{{ $line->status?->label() ?? '—' }}</span></td>
                                </tr>
                            @empty
                                @if($borrowing->inventoryItem)
                                    @php $inv = $borrowing->inventoryItem; @endphp
                                    <tr>
                                        <td><a href="{{ route('inventory.show', $inv) }}"><code>{{ $inv->inventory_number }}</code></a></td>
                                        <td>{{ $inv->name }}</td>
                                        <td>{{ $inv->category?->name ?? '—' }}</td>
                                        <td>{{ $inv->subCategory?->name ?? '—' }}</td>
                                        <td class="small">{{ $inv->full_location ?? '—' }}</td>
                                        <td><span class="badge bg-{{ $borrowing->status?->badgeColor() ?? 'secondary' }}">{{ $borrowing->status?->label() }}</span></td>
                                    </tr>
                                @else
                                    <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada barang.</td></tr>
                                @endif
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
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
