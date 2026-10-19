<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Approval {{ $borrowing->approval_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 980px; margin: 2rem auto; padding: 0 12px; }
        .brand { color: #1F3C88; }
        .card { border: 0; box-shadow: 0 8px 24px rgba(15,23,42,.06); border-radius: 14px; }
        .qr img { width: 140px; height: 140px; max-width: 100%; }
        .approval-items { min-width: 860px; }
        .approval-items th,
        .approval-items td { vertical-align: top; overflow-wrap: anywhere; }
        .approval-items .col-number { width: 22%; }
        .approval-items .col-name { width: 14%; }
        .approval-items .col-cat { width: 12%; }
        .approval-items .col-sub { width: 12%; }
        .approval-items .col-loc { width: 26%; }
        .approval-items .col-status { width: 14%; }
        .approval-summary { font-size: .95rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="text-center mb-3">
        <img src="{{ asset('images/logo-undira.png') }}" alt="UNDIRA" style="height:52px" onerror="this.style.display='none'">
        <h1 class="h4 brand mt-2 mb-0">Detail Approval Peminjaman</h1>
        <div class="text-muted small">Universitas Dian Nusantara</div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="text-muted small">Nomor Approval</div>
                    <div class="fs-5 fw-bold">{{ $borrowing->approval_number }}</div>
                    <span class="badge bg-{{ $borrowing->status?->badgeColor() ?? 'secondary' }} mt-2">{{ $borrowing->status?->label() }}</span>
                </div>
                @if(!empty($qrDataUri))
                <div class="qr text-center">
                    <img src="{{ $qrDataUri }}" alt="QR">
                </div>
                @endif
            </div>

            <hr>
            <table class="table table-sm mb-3">
                <tr><th style="width:40%">Nama Peminjam</th><td>{{ $borrowing->borrower_name }}</td></tr>
                <tr><th>Email</th><td>{{ $borrowing->borrower_email ?? '—' }}</td></tr>
                <tr><th>Unit</th><td>{{ $borrowing->borrower_affiliation ?: '—' }}</td></tr>
                <tr><th>Tanggal Pinjam</th><td>{{ $borrowing->plannedScheduleText('borrow_date') }}</td></tr>
                <tr><th>Tanggal Kembali</th><td>{{ $borrowing->plannedScheduleText('expected_return_date') }}</td></tr>
                <tr><th>Jumlah Item</th><td>{{ isset($items) && $items->count() ? $items->count() : ($item ? 1 : 0) }}</td></tr>
                <tr><th>Approved By</th><td>{{ $borrowing->approvedBy?->name ?? '—' }}</td></tr>
                <tr><th>Approved At</th><td>{{ $borrowing->approved_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                @if($borrowing->rejected_at)
                <tr><th>Rejected At</th><td>{{ $borrowing->rejected_at?->format('d/m/Y H:i') }}</td></tr>
                <tr><th>Alasan Penolakan</th><td>{{ $borrowing->rejection_reason ?? '—' }}</td></tr>
                @endif
                <tr><th>Keperluan</th><td>{{ $borrowing->notes ?: '—' }}</td></tr>
                <tr><th>Catatan Approval</th><td>{{ $borrowing->approval_notes ?: '—' }}</td></tr>
            </table>

            @php
                $approvalLines = isset($items) && $items->count() ? $items : collect();
                $approvalCounts = ['Disetujui' => 0, 'Ditolak' => 0, 'Menunggu' => 0, 'Status Tidak Tersedia' => 0];
                foreach ($approvalLines as $approvalLine) {
                    $approvalCounts[$approvalLine->approvalPresentation()[0]]++;
                }
                if ($approvalLines->isEmpty() && $item) {
                    $approvalCounts['Status Tidak Tersedia'] = 1;
                }
            @endphp
            <h2 class="h6">Daftar Barang</h2>
            @if($approvalLines->isNotEmpty() || $item)
            <p class="approval-summary mb-2">
                <strong>Ringkasan Persetujuan.</strong>
                Jumlah Item: {{ $approvalLines->isNotEmpty() ? $approvalLines->count() : 1 }}.
                Disetujui: {{ $approvalCounts['Disetujui'] }}.
                Ditolak: {{ $approvalCounts['Ditolak'] }}.
                @if($approvalCounts['Menunggu'] > 0)
                    Menunggu: {{ $approvalCounts['Menunggu'] }}.
                @endif
                @if($approvalCounts['Status Tidak Tersedia'] > 0)
                    Status Tidak Tersedia: {{ $approvalCounts['Status Tidak Tersedia'] }}.
                @endif
            </p>
            @endif
            <div class="table-responsive">
                <table class="table table-sm approval-items mb-0">
                    <thead>
                        <tr>
                            <th class="col-number">Nomor</th>
                            <th class="col-name">Nama</th>
                            <th class="col-cat">Kategori</th>
                            <th class="col-sub">Sub Kategori</th>
                            <th class="col-loc">Lokasi</th>
                            <th class="col-status">Status Persetujuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($approvalLines as $line)
                            @php
                                $inv = $line->inventoryItem;
                                [$approvalLabel, $approvalColor] = $line->approvalPresentation();
                            @endphp
                            <tr>
                                <td class="col-number"><code>{{ $inv?->inventory_number ?? '—' }}</code></td>
                                <td class="col-name">{{ $inv?->name ?? '—' }}</td>
                                <td class="col-cat">{{ $inv?->category?->name ?? '—' }}</td>
                                <td class="col-sub">{{ $inv?->subCategory?->name ?? '—' }}</td>
                                <td class="col-loc small">{{ $inv?->full_location ?? '—' }}</td>
                                <td class="col-status"><span class="badge text-bg-{{ $approvalColor }}">{{ $approvalLabel }}</span></td>
                            </tr>
                        @empty
                            @if($item)
                            <tr>
                                <td class="col-number"><code>{{ $item->inventory_number }}</code></td>
                                <td class="col-name">{{ $item->name }}</td>
                                <td class="col-cat">{{ $item->category?->name ?? '—' }}</td>
                                <td class="col-sub">{{ $item->subCategory?->name ?? '—' }}</td>
                                <td class="col-loc small">{{ $item->full_location ?? '—' }}</td>
                                <td class="col-status"><span class="badge text-bg-secondary">Status Tidak Tersedia</span></td>
                            </tr>
                            @else
                            <tr><td colspan="6" class="text-muted">Tidak ada barang.</td></tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-center">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('borrowing.approval.receipt', $borrowing->approval_number) }}">Lihat Receipt</a>
        <a class="btn btn-primary btn-sm" href="{{ route('borrowing.approval.receipt.pdf', $borrowing->approval_number) }}">Download PDF</a>
    </div>
</div>
</body>
</html>
