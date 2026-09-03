@extends('layouts.app')

@section('title', 'Detail Peminjaman - Inventaris UNDIRA')

@php
    use App\Enums\BorrowingItemStatus;
    use App\Enums\BorrowingStatus;
    $lines = $borrowing->items->isNotEmpty() ? $borrowing->items : collect();
    $itemCount = $lines->count() ?: ($borrowing->inventoryItem ? 1 : 0);
    $canApproveTx = auth()->user()?->can('approve', $borrowing);
    $canHandover = auth()->user()?->can('handover', $borrowing);
    $canReturn = auth()->user()?->can('update', $borrowing)
        && in_array($borrowing->status, [BorrowingStatus::Disetujui, BorrowingStatus::Dipinjam, BorrowingStatus::Terlambat], true);
    $openBorrowed = $lines->filter(fn ($l) => $l->status === BorrowingItemStatus::Dipinjam);
    $expectedPast = $borrowing->expected_return_date
        && $borrowing->expected_return_date->copy()->startOfDay()->lt(now()->copy()->startOfDay());
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
    @if($canApproveTx)
    <form action="{{ route('borrowings.approve', $borrowing) }}" method="POST" class="d-inline js-once-submit">
        @csrf
        <button type="submit" class="btn btn-success btn-sm" title="Setujui semua item yang masih menunggu">
            <i class="fas fa-check-double me-1"></i>Setujui Semua Menunggu
        </button>
    </form>
    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectBorrowingModal">
        <i class="fas fa-times me-1"></i>Tolak Semua Menunggu
    </button>
    @endif
</x-page-header>

<div class="row">
    <div class="col-lg-4">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Data Peminjam</h5></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Nomor Approval</th><td>{{ $borrowing->approval_number ?? '—' }}</td></tr>
                    <tr><th>Peminjam</th><td>{{ $borrowing->borrower_name }}</td></tr>
                    <tr><th>Email</th><td>{{ $borrowing->borrower_email ?? '-' }}</td></tr>
                    <tr><th>Telepon</th><td>{{ $borrowing->borrower_phone ?? '-' }}</td></tr>
                    <tr>
                        <th>Lokasi Kampus</th>
                        <td>
                            @php
                                $detailCampusNames = $campusNames ?? $borrowing->campusNames();
                                $detailCampusSummary = $campusSummary ?? $borrowing->campusSummary();
                            @endphp
                            @if($detailCampusNames->count() > 1)
                                <span class="badge bg-warning text-dark">Multi Kampus</span>
                                <div class="small text-muted mt-1">{{ $detailCampusNames->implode(', ') }}</div>
                            @else
                                {{ $detailCampusSummary }}
                            @endif
                        </td>
                    </tr>
                    <tr><th>Tgl Pinjam (Rencana)</th><td>{{ $borrowing->borrow_date?->format('d/m/Y') }}</td></tr>
                    <tr><th>Tgl Kembali (Rencana)</th><td>{{ $borrowing->expected_return_date?->format('d/m/Y') ?? '-' }}</td></tr>
                    <tr><th>Tgl Kembali (Aktual Header)</th><td>{{ $borrowing->return_date?->format('d/m/Y') ?? '-' }}</td></tr>
                    <tr>
                        <th>Status Transaksi</th>
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
                    <tr><th>Keperluan</th><td>{{ $borrowing->notes ?? '-' }}</td></tr>
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

    <div class="col-lg-8">
        <div class="card card-undira mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Daftar Barang</h5>
                @if($canReturn && $openBorrowed->isNotEmpty())
                <form action="{{ route('borrowings.return-items', $borrowing) }}" method="POST" class="d-flex flex-wrap align-items-end gap-2 js-once-submit" id="returnItemsForm">
                    @csrf
                    <input type="hidden" name="return_all" value="1">
                    <div>
                        <label class="form-label small mb-0" for="returned_at_bulk">Tgl Pengembalian Aktual</label>
                        <input type="datetime-local" name="returned_at" id="returned_at_bulk" class="form-control form-control-sm"
                               value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <button type="submit" class="btn btn-sm btn-undira">
                        <i class="fas fa-check-double me-1"></i>Kembalikan Semua Dipinjam
                    </button>
                </form>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Nomor Inventaris</th>
                                <th>Nama / Kategori</th>
                                <th>Lokasi</th>
                                <th>Status Persetujuan</th>
                                <th>Status Peminjaman</th>
                                <th>Tgl Pinjam Aktual</th>
                                <th>Tgl Kembali Aktual</th>
                                <th style="min-width:160px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $line)
                                @php
                                    $inv = $line->inventoryItem;
                                    $isOverdueItem = $expectedPast
                                        && $line->status === BorrowingItemStatus::Dipinjam
                                        && blank($line->returned_at);
                                    $approvalBadge = match ($line->status) {
                                        BorrowingItemStatus::MenungguPersetujuan => ['warning', 'Menunggu'],
                                        BorrowingItemStatus::Disetujui,
                                        BorrowingItemStatus::Dipinjam,
                                        BorrowingItemStatus::Dikembalikan => ['success', 'Disetujui'],
                                        BorrowingItemStatus::Ditolak => ['danger', 'Ditolak'],
                                        default => ['secondary', $line->status?->label() ?? '—'],
                                    };
                                    $loanBadge = match (true) {
                                        $line->status === BorrowingItemStatus::Ditolak => ['secondary', '—'],
                                        $line->status === BorrowingItemStatus::MenungguPersetujuan => ['warning', 'Menunggu'],
                                        $line->status === BorrowingItemStatus::Disetujui => ['info', 'Disetujui'],
                                        $isOverdueItem => ['warning', 'Terlambat'],
                                        $line->status === BorrowingItemStatus::Dipinjam => ['primary', 'Dipinjam'],
                                        $line->status === BorrowingItemStatus::Dikembalikan => ['secondary', 'Dikembalikan'],
                                        default => ['secondary', $line->status?->label() ?? '—'],
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        @if($inv)
                                            <a href="{{ route('inventory.show', $inv) }}"><code>{{ $inv->inventory_number }}</code></a>
                                        @else —
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $inv?->name ?? '—' }}</div>
                                        <div class="small text-muted">
                                            {{ $inv?->category?->name ?? '—' }}
                                            @if($inv?->subCategory)
                                                / {{ $inv->subCategory->name }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="small">{{ $inv?->full_location ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $approvalBadge[0] }}">{{ $approvalBadge[1] }}</span>
                                        @if($line->status === BorrowingItemStatus::Ditolak && filled($line->rejection_reason))
                                            <div class="small text-muted mt-1">{{ $line->rejection_reason }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-{{ $loanBadge[0] }}">{{ $loanBadge[1] }}</span></td>
                                    <td class="small">{{ $line->borrowed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="small">{{ $line->returned_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @if($canApproveTx && $line->status === BorrowingItemStatus::MenungguPersetujuan)
                                                <form action="{{ route('borrowings.items.approve', [$borrowing, $line]) }}" method="POST" class="js-once-submit">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm">Setujui</button>
                                                </form>
                                                <button type="button"
                                                        class="btn btn-outline-danger btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#rejectItemModal"
                                                        data-reject-url="{{ route('borrowings.items.reject', [$borrowing, $line]) }}"
                                                        data-item-number="{{ $inv?->inventory_number ?? '—' }}"
                                                        data-item-name="{{ $inv?->name ?? '—' }}">
                                                    Tolak
                                                </button>
                                            @endif

                                            @if($canHandover && $line->status === BorrowingItemStatus::Disetujui)
                                                <form action="{{ route('borrowings.items.handover', [$borrowing, $line]) }}" method="POST" class="js-once-submit border rounded p-1">
                                                    @csrf
                                                    <label class="form-label small mb-0">Tgl Pinjam Aktual</label>
                                                    <input type="datetime-local" name="borrowed_at" class="form-control form-control-sm mb-1"
                                                           value="{{ now()->format('Y-m-d\TH:i') }}">
                                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                                        <i class="fas fa-handshake me-1"></i>Serahkan
                                                    </button>
                                                </form>
                                            @endif

                                            @if($canReturn && $line->status === BorrowingItemStatus::Dipinjam)
                                                <form action="{{ route('borrowings.return-items', $borrowing) }}" method="POST" class="js-once-submit border rounded p-1">
                                                    @csrf
                                                    <input type="hidden" name="item_ids[]" value="{{ $line->id }}">
                                                    <label class="form-label small mb-0">Tgl Kembali Aktual</label>
                                                    <input type="datetime-local" name="returned_at" class="form-control form-control-sm mb-1"
                                                           value="{{ now()->format('Y-m-d\TH:i') }}">
                                                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                                        <i class="fas fa-undo me-1"></i>Kembalikan
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                @if($borrowing->inventoryItem)
                                    @php $inv = $borrowing->inventoryItem; @endphp
                                    <tr>
                                        <td><a href="{{ route('inventory.show', $inv) }}"><code>{{ $inv->inventory_number }}</code></a></td>
                                        <td>
                                            <div class="fw-semibold">{{ $inv->name }}</div>
                                            <div class="small text-muted">{{ $inv->category?->name ?? '—' }}</div>
                                        </td>
                                        <td class="small">{{ $inv->full_location ?? '—' }}</td>
                                        <td><span class="badge bg-{{ $borrowing->status?->badgeColor() ?? 'secondary' }}">{{ $borrowing->status?->label() }}</span></td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td>—</td>
                                    </tr>
                                @else
                                    <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada barang.</td></tr>
                                @endif
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canApproveTx)
<div class="modal fade" id="rejectBorrowingModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('borrowings.reject', $borrowing) }}" method="POST" class="modal-content js-once-submit">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Tolak Semua Item Menunggu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak Inventaris</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="rejectItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content js-once-submit" id="rejectItemForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Tolak Inventaris</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1"><strong>Nomor:</strong> <span id="rejectItemNumber">—</span></p>
                <p class="mb-3"><strong>Nama:</strong> <span id="rejectItemName">—</span></p>
                <label class="form-label" for="rejectItemReason">Alasan <span class="text-danger">*</span></label>
                <textarea name="rejection_reason" id="rejectItemReason" class="form-control" rows="3" required minlength="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak Inventaris</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('form.js-once-submit').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            var original = btn.innerHTML;
            btn.dataset.originalHtml = original;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...';
        });
    });

    var rejectModal = document.getElementById('rejectItemModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;
            var form = document.getElementById('rejectItemForm');
            form.action = button.getAttribute('data-reject-url') || '';
            document.getElementById('rejectItemNumber').textContent = button.getAttribute('data-item-number') || '—';
            document.getElementById('rejectItemName').textContent = button.getAttribute('data-item-name') || '—';
            document.getElementById('rejectItemReason').value = '';
        });
    }
})();
</script>
@endpush
