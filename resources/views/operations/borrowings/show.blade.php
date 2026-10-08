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
    $pendingLines = $lines->filter(fn ($l) => $l->status === BorrowingItemStatus::MenungguPersetujuan);
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
  min-width: 220px;
  width: 220px;
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
.borrowing-decision {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.borrowing-decision__btns {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.borrowing-decision .btn.is-selected.btn-outline-success {
  background: #198754;
  border-color: #198754;
  color: #fff;
}
.borrowing-decision .btn.is-selected.btn-outline-danger {
  background: #dc3545;
  border-color: #dc3545;
  color: #fff;
}
.borrowing-save-bar {
  border-top: 1px solid var(--ui-border, #e5e7eb);
  padding: 1rem 1.15rem;
  background: var(--ui-surface, #fff);
}
body.dark-mode .borrowing-save-bar {
  background: var(--dm-card, #1F2937);
  border-top-color: var(--dm-border, #374151);
}
body.dark-mode .borrowing-decision .btn.is-selected.btn-outline-success {
  background: #157347;
  border-color: #157347;
  color: #fff;
}
body.dark-mode .borrowing-decision .btn.is-selected.btn-outline-danger {
  background: #bb2d3b;
  border-color: #bb2d3b;
  color: #fff;
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
                    <tr>
                        <th>Dokumen Pendukung</th>
                        <td>
                            @if($borrowing->hasSupportingDocument())
                                @php $docService = app(\App\Services\BorrowingSupportingDocumentService::class); @endphp
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    @if($docService->isImage($borrowing))
                                        <img src="{{ route('borrowings.supporting-document.view', $borrowing) }}" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid var(--bs-border-color,#dee2e6)">
                                    @else
                                        <span class="d-inline-flex align-items-center justify-content-center" style="width:72px;height:72px;border-radius:6px;border:1px solid var(--bs-border-color,#dee2e6)"><i class="fas fa-file-pdf fa-2x text-danger"></i></span>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-break">{{ $borrowing->supporting_document_original_name ?: 'dokumen-pendukung.pdf' }}</div>
                                        <div class="small text-muted">{{ $docService->humanSize($borrowing->supporting_document_size) }}</div>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('borrowings.supporting-document.view', $borrowing) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" data-no-loader="1">
                                        <i class="fas fa-eye me-1"></i>Lihat Dokumen
                                    </a>
                                    <a href="{{ route('borrowings.supporting-document.download', $borrowing) }}" class="btn btn-sm btn-outline-secondary" data-no-loader="1">
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
                                </div>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
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
                                <span class="badge bg-{{ $approvalBadge[0] }} js-approval-badge" data-default-color="{{ $approvalBadge[0] }}" data-default-label="{{ $approvalBadge[1] }}">{{ $approvalBadge[1] }}</span>
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
                                        <div class="borrowing-decision js-item-decision" data-item-id="{{ $line->id }}">
                                            <div class="borrowing-decision__btns">
                                                <button type="button"
                                                        class="btn btn-outline-success btn-sm js-decide-btn"
                                                        data-action="approve"
                                                        aria-pressed="false">
                                                    <i class="fas fa-check me-1"></i>Setujui
                                                </button>
                                                <button type="button"
                                                        class="btn btn-outline-danger btn-sm js-decide-btn"
                                                        data-action="reject"
                                                        aria-pressed="false">
                                                    <i class="fas fa-times me-1"></i>Tolak
                                                </button>
                                            </div>
                                            <input type="text"
                                                   class="form-control form-control-sm js-reject-reason d-none"
                                                   maxlength="1000"
                                                   placeholder="Alasan penolakan (opsional)"
                                                   aria-label="Alasan penolakan">
                                        </div>
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
        @if($canApproveTx && $pendingLines->isNotEmpty())
        <div class="borrowing-save-bar">
            @if($errors->has('items') || $errors->has('decisions') || $errors->has('decisions.0.action'))
                <div class="alert alert-warning py-2 small mb-3" role="alert">
                    {{ $errors->first('items') ?: ($errors->first('decisions') ?: 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak terlebih dahulu.') }}
                </div>
            @endif
            <div id="saveApprovalClientError" class="alert alert-warning py-2 small mb-3 d-none" role="alert">
                Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak terlebih dahulu.
            </div>
            <p class="small text-muted mb-2">Pastikan seluruh keputusan inventaris sudah benar sebelum menyimpan.</p>
            <form id="saveApprovalsForm" method="POST" action="{{ route('borrowings.save-approvals', $borrowing) }}" class="js-once-submit">
                @csrf
                <div id="saveApprovalsFields"></div>
                <button type="button"
                        class="btn btn-success"
                        id="openSaveApprovalModal"
                        disabled>
                    <i class="fas fa-save me-1"></i>Simpan Approval
                </button>
            </form>
        </div>
        @endif
    </div>
</div>

@if($canApproveTx && $pendingLines->isNotEmpty())
<div class="modal fade" id="saveApprovalModal" tabindex="-1" aria-labelledby="saveApprovalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="saveApprovalModalLabel">Konfirmasi Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Anda akan menyimpan keputusan untuk <strong id="saveApprovalTotal">0</strong> inventaris:</p>
                <ul class="mb-3">
                    <li><span class="text-success">✓ Disetujui:</span> <strong id="saveApprovalApprovedCount">0</strong></li>
                    <li><span class="text-danger">✕ Ditolak:</span> <strong id="saveApprovalRejectedCount">0</strong></li>
                </ul>
                <p class="mb-0 small text-muted">Pastikan keputusan sudah benar. Email akan dikirim kepada peminjam setelah penyimpanan berhasil.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="confirmSaveApproval">
                    <i class="fas fa-save me-1"></i>Simpan Approval
                </button>
            </div>
        </div>
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

    var decisionRows = Array.prototype.slice.call(document.querySelectorAll('.js-item-decision'));
    var openSaveBtn = document.getElementById('openSaveApprovalModal');
    var confirmSaveBtn = document.getElementById('confirmSaveApproval');
    var saveForm = document.getElementById('saveApprovalsForm');
    var saveFields = document.getElementById('saveApprovalsFields');
    var clientError = document.getElementById('saveApprovalClientError');
    var saveModalEl = document.getElementById('saveApprovalModal');
    var submitting = false;

    function setBadge(row, action) {
        var tr = row.closest('tr');
        var badge = tr ? tr.querySelector('.js-approval-badge') : null;
        if (!badge) return;
        badge.classList.remove('bg-warning', 'bg-success', 'bg-danger', 'bg-secondary');
        if (action === 'approve') {
            badge.classList.add('bg-success');
            badge.textContent = '✓ Disetujui';
        } else if (action === 'reject') {
            badge.classList.add('bg-danger');
            badge.textContent = '✕ Ditolak';
        } else {
            badge.classList.add('bg-' + (badge.getAttribute('data-default-color') || 'warning'));
            badge.textContent = badge.getAttribute('data-default-label') || 'Menunggu';
        }
    }

    function collectDecisions() {
        return decisionRows.map(function (row) {
            var selected = row.querySelector('.js-decide-btn.is-selected');
            var reasonInput = row.querySelector('.js-reject-reason');
            return {
                id: row.getAttribute('data-item-id'),
                action: selected ? selected.getAttribute('data-action') : '',
                rejection_reason: reasonInput ? reasonInput.value.trim() : '',
                complete: !!selected
            };
        });
    }

    function refreshSaveState() {
        var decisions = collectDecisions();
        var complete = decisions.length > 0 && decisions.every(function (d) { return d.complete; });
        if (openSaveBtn) {
            openSaveBtn.disabled = !complete;
        }
        if (complete && clientError) {
            clientError.classList.add('d-none');
        }
    }

    decisionRows.forEach(function (row) {
        row.querySelectorAll('.js-decide-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.getAttribute('data-action');
                row.querySelectorAll('.js-decide-btn').forEach(function (other) {
                    var selected = other === btn;
                    other.classList.toggle('is-selected', selected);
                    other.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });
                var reason = row.querySelector('.js-reject-reason');
                if (reason) {
                    reason.classList.toggle('d-none', action !== 'reject');
                    if (action !== 'reject') reason.value = '';
                }
                setBadge(row, action);
                refreshSaveState();
            });
        });
    });

    refreshSaveState();

    function showIncompleteMessage() {
        if (clientError) {
            clientError.classList.remove('d-none');
            clientError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    if (openSaveBtn && saveModalEl) {
        openSaveBtn.addEventListener('click', function () {
            var decisions = collectDecisions();
            if (!decisions.length || decisions.some(function (d) { return !d.complete; })) {
                showIncompleteMessage();
                return;
            }
            var approved = decisions.filter(function (d) { return d.action === 'approve'; }).length;
            var rejected = decisions.filter(function (d) { return d.action === 'reject'; }).length;
            document.getElementById('saveApprovalTotal').textContent = String(decisions.length);
            document.getElementById('saveApprovalApprovedCount').textContent = String(approved);
            document.getElementById('saveApprovalRejectedCount').textContent = String(rejected);
            bootstrap.Modal.getOrCreateInstance(saveModalEl).show();
        });
    }

    if (confirmSaveBtn && saveForm && saveFields) {
        confirmSaveBtn.addEventListener('click', function () {
            if (submitting) return;
            var decisions = collectDecisions();
            if (!decisions.length || decisions.some(function (d) { return !d.complete; })) {
                showIncompleteMessage();
                return;
            }

            submitting = true;
            confirmSaveBtn.disabled = true;
            confirmSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...';
            if (openSaveBtn) openSaveBtn.disabled = true;

            saveFields.innerHTML = '';
            decisions.forEach(function (d, index) {
                ['id', 'action', 'rejection_reason'].forEach(function (key) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'decisions[' + index + '][' + key + ']';
                    input.value = d[key] || '';
                    saveFields.appendChild(input);
                });
            });

            saveForm.submit();
        });
    }
})();
</script>
@endpush
