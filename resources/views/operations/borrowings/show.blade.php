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
    $nowLocal = now()->format('Y-m-d\TH:i');
@endphp

@push('styles')
<style>
.borrowing-items-wrap {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.borrowing-items-table {
  min-width: 1120px;
  margin-bottom: 0;
}
.borrowing-items-table thead th {
  white-space: nowrap;
  font-size: 0.78rem;
  letter-spacing: 0.01em;
  vertical-align: middle;
}
.borrowing-items-table td {
  vertical-align: middle;
}
.borrowing-items-table .col-number { min-width: 168px; max-width: 210px; }
.borrowing-items-table .col-name { min-width: 150px; max-width: 220px; }
.borrowing-items-table .col-loc { min-width: 160px; max-width: 210px; }
.borrowing-items-table .col-status { min-width: 118px; white-space: nowrap; }
.borrowing-items-table .col-date { min-width: 118px; white-space: nowrap; }
.borrowing-items-table .col-aksi {
  position: sticky;
  right: 0;
  min-width: 196px;
  width: 196px;
  background: var(--ui-surface, #fff);
  box-shadow: -8px 0 12px -10px rgba(15, 23, 42, 0.28);
  z-index: 2;
}
.borrowing-items-table thead .col-aksi {
  z-index: 3;
}
.borrowing-loc {
  line-height: 1.35;
  font-size: 0.8rem;
}
.borrowing-loc__campus { font-weight: 600; }
.borrowing-loc__meta { color: var(--ui-muted, #64748B); }
.borrowing-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.borrowing-actions .btn {
  white-space: nowrap;
}
body.dark-mode .borrowing-items-table .col-aksi {
  background: var(--dm-card, #1F2937);
  box-shadow: -8px 0 12px -10px rgba(0, 0, 0, 0.55);
}
body.dark-mode .borrowing-loc__meta {
  color: var(--dm-muted, #B4BCC8);
}
</style>
@endpush

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
    <div class="col-lg-6">
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
    </div>
    <div class="col-lg-6">
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

<div class="card card-undira mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Daftar Barang</h5>
        @if($canReturn && $openBorrowed->isNotEmpty())
        <form action="{{ route('borrowings.return-items', $borrowing) }}" method="POST" class="d-flex flex-wrap align-items-end gap-2 js-once-submit">
            @csrf
            <input type="hidden" name="return_all" value="1">
            <div>
                <label class="form-label small mb-0" for="returned_at_bulk">Tgl Pengembalian Aktual</label>
                <input type="datetime-local" name="returned_at" id="returned_at_bulk" class="form-control form-control-sm"
                       value="{{ $nowLocal }}">
            </div>
            <button type="submit" class="btn btn-sm btn-undira">
                <i class="fas fa-check-double me-1"></i>Kembalikan Semua Dipinjam
            </button>
        </form>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="borrowing-items-wrap table-responsive">
            <table class="table table-sm table-hover borrowing-items-table">
                <thead>
                    <tr>
                        <th class="col-number">No. Inventaris</th>
                        <th class="col-name">Nama / Kategori</th>
                        <th class="col-loc">Lokasi</th>
                        <th class="col-status">Status Persetujuan</th>
                        <th class="col-status">Status Peminjaman</th>
                        <th class="col-date">Tgl Pinjam Aktual</th>
                        <th class="col-date">Tgl Kembali Aktual</th>
                        <th class="col-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lines as $line)
                        @php
                            $inv = $line->inventoryItem;
                            $room = $inv?->room;
                            $floor = $room?->floor;
                            $building = $floor?->building;
                            $campusName = $building?->campus?->name;
                            $buildingName = $building?->name;
                            $floorLabel = $floor?->floor_number
                                ? 'Lt. '.$floor->floor_number
                                : $floor?->name;
                            $roomName = $room?->name;
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
                                $line->status === BorrowingItemStatus::Disetujui => ['info', 'Belum Dipinjam'],
                                $isOverdueItem => ['warning', 'Terlambat'],
                                $line->status === BorrowingItemStatus::Dipinjam => ['primary', 'Dipinjam'],
                                $line->status === BorrowingItemStatus::Dikembalikan => ['secondary', 'Dikembalikan'],
                                default => ['secondary', $line->status?->label() ?? '—'],
                            };
                        @endphp
                        <tr>
                            <td class="col-number">
                                @if($inv)
                                    <a href="{{ route('inventory.show', $inv) }}"><code>{{ $inv->inventory_number }}</code></a>
                                @else —
                                @endif
                            </td>
                            <td class="col-name">
                                <div class="fw-semibold">{{ $inv?->name ?? '—' }}</div>
                                <div class="small text-muted">
                                    {{ $inv?->category?->name ?? '—' }}
                                    @if($inv?->subCategory)
                                        · {{ $inv->subCategory->name }}
                                    @endif
                                </div>
                            </td>
                            <td class="col-loc">
                                @if($campusName || $buildingName || $roomName)
                                    <div class="borrowing-loc">
                                        @if($campusName)
                                            <div class="borrowing-loc__campus">{{ $campusName }}</div>
                                        @endif
                                        @if($buildingName || $floorLabel)
                                            <div class="borrowing-loc__meta">
                                                {{ collect([$buildingName, $floorLabel])->filter()->implode(' · ') }}
                                            </div>
                                        @endif
                                        @if($roomName)
                                            <div>{{ $roomName }}</div>
                                        @endif
                                    </div>
                                @else
                                    <span class="small text-muted">{{ $inv?->full_location ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="col-status">
                                <span class="badge bg-{{ $approvalBadge[0] }}">{{ $approvalBadge[1] }}</span>
                                @if($line->status === BorrowingItemStatus::Ditolak && filled($line->rejection_reason))
                                    <div class="small text-muted mt-1">{{ $line->rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="col-status"><span class="badge bg-{{ $loanBadge[0] }}">{{ $loanBadge[1] }}</span></td>
                            <td class="col-date">{{ $line->borrowed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="col-date">{{ $line->returned_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="col-aksi">
                                <div class="borrowing-actions">
                                    @if($canApproveTx && $line->status === BorrowingItemStatus::MenungguPersetujuan)
                                        <button type="button"
                                                class="btn btn-success btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#approveItemModal"
                                                data-approve-url="{{ route('borrowings.items.approve', [$borrowing, $line]) }}"
                                                data-item-number="{{ $inv?->inventory_number ?? '—' }}"
                                                data-item-name="{{ $inv?->name ?? '—' }}"
                                                data-item-campus="{{ $campusName ?? '—' }}">
                                            <i class="fas fa-check me-1"></i>Setujui
                                        </button>
                                        <button type="button"
                                                class="btn btn-danger btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#rejectItemModal"
                                                data-reject-url="{{ route('borrowings.items.reject', [$borrowing, $line]) }}"
                                                data-item-number="{{ $inv?->inventory_number ?? '—' }}"
                                                data-item-name="{{ $inv?->name ?? '—' }}"
                                                data-item-campus="{{ $campusName ?? '—' }}">
                                            <i class="fas fa-times me-1"></i>Tolak
                                        </button>
                                    @endif

                                    @if($canHandover && $line->status === BorrowingItemStatus::Disetujui)
                                        <button type="button"
                                                class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#handoverItemModal"
                                                data-handover-url="{{ route('borrowings.items.handover', [$borrowing, $line]) }}"
                                                data-item-number="{{ $inv?->inventory_number ?? '—' }}"
                                                data-item-name="{{ $inv?->name ?? '—' }}">
                                            <i class="fas fa-handshake me-1"></i>Tandai Dipinjam
                                        </button>
                                    @endif

                                    @if($canReturn && $line->status === BorrowingItemStatus::Dipinjam)
                                        <button type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#returnItemModal"
                                                data-return-url="{{ route('borrowings.return-items', $borrowing) }}"
                                                data-item-id="{{ $line->id }}"
                                                data-item-number="{{ $inv?->inventory_number ?? '—' }}"
                                                data-item-name="{{ $inv?->name ?? '—' }}">
                                            <i class="fas fa-undo me-1"></i>Tandai Dikembalikan
                                        </button>
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
                                <td class="col-aksi">—</td>
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

<div class="modal fade" id="approveItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content js-once-submit" id="approveItemForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Persetujuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Apakah Anda yakin ingin menyetujui inventaris ini?</p>
                <p class="mb-1"><strong>Nomor Inventaris:</strong> <span id="approveItemNumber">—</span></p>
                <p class="mb-1"><strong>Nama:</strong> <span id="approveItemName">—</span></p>
                <p class="mb-0"><strong>Kampus:</strong> <span id="approveItemCampus">—</span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">Setujui Inventaris</button>
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
                <p class="mb-1"><strong>Nomor Inventaris:</strong> <span id="rejectItemNumber">—</span></p>
                <p class="mb-1"><strong>Nama:</strong> <span id="rejectItemName">—</span></p>
                <p class="mb-3"><strong>Kampus:</strong> <span id="rejectItemCampus">—</span></p>
                <label class="form-label" for="rejectItemReason">Alasan Penolakan <span class="text-danger">*</span></label>
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

@if($canHandover)
<div class="modal fade" id="handoverItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content js-once-submit" id="handoverItemForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Tandai Dipinjam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1"><strong>Nomor Inventaris:</strong> <span id="handoverItemNumber">—</span></p>
                <p class="mb-3"><strong>Nama:</strong> <span id="handoverItemName">—</span></p>
                <label class="form-label" for="handoverBorrowedAt">Tanggal Peminjaman Aktual</label>
                <input type="datetime-local" name="borrowed_at" id="handoverBorrowedAt" class="form-control" value="{{ $nowLocal }}">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Serahkan Barang</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canReturn)
<div class="modal fade" id="returnItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content js-once-submit" id="returnItemForm">
            @csrf
            <input type="hidden" name="item_ids[]" id="returnItemId" value="">
            <div class="modal-header">
                <h5 class="modal-title">Tandai Dikembalikan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1"><strong>Nomor Inventaris:</strong> <span id="returnItemNumber">—</span></p>
                <p class="mb-3"><strong>Nama:</strong> <span id="returnItemName">—</span></p>
                <label class="form-label" for="returnReturnedAt">Tanggal Pengembalian Aktual</label>
                <input type="datetime-local" name="returned_at" id="returnReturnedAt" class="form-control" value="{{ $nowLocal }}">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Pengembalian</button>
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
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...';
        });
    });

    function bindModal(id, fill) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;
            fill(button);
        });
    }

    bindModal('approveItemModal', function (button) {
        document.getElementById('approveItemForm').action = button.getAttribute('data-approve-url') || '';
        document.getElementById('approveItemNumber').textContent = button.getAttribute('data-item-number') || '—';
        document.getElementById('approveItemName').textContent = button.getAttribute('data-item-name') || '—';
        document.getElementById('approveItemCampus').textContent = button.getAttribute('data-item-campus') || '—';
    });

    bindModal('rejectItemModal', function (button) {
        document.getElementById('rejectItemForm').action = button.getAttribute('data-reject-url') || '';
        document.getElementById('rejectItemNumber').textContent = button.getAttribute('data-item-number') || '—';
        document.getElementById('rejectItemName').textContent = button.getAttribute('data-item-name') || '—';
        document.getElementById('rejectItemCampus').textContent = button.getAttribute('data-item-campus') || '—';
        document.getElementById('rejectItemReason').value = '';
    });

    bindModal('handoverItemModal', function (button) {
        document.getElementById('handoverItemForm').action = button.getAttribute('data-handover-url') || '';
        document.getElementById('handoverItemNumber').textContent = button.getAttribute('data-item-number') || '—';
        document.getElementById('handoverItemName').textContent = button.getAttribute('data-item-name') || '—';
    });

    bindModal('returnItemModal', function (button) {
        document.getElementById('returnItemForm').action = button.getAttribute('data-return-url') || '';
        document.getElementById('returnItemId').value = button.getAttribute('data-item-id') || '';
        document.getElementById('returnItemNumber').textContent = button.getAttribute('data-item-number') || '—';
        document.getElementById('returnItemName').textContent = button.getAttribute('data-item-name') || '—';
    });
})();
</script>
@endpush
