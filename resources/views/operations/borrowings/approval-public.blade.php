<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Approval {{ $borrowing->approval_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .wrap { max-width: 720px; margin: 2rem auto; }
        .brand { color: #1F3C88; }
        .card { border: 0; box-shadow: 0 8px 24px rgba(15,23,42,.06); border-radius: 14px; }
        .qr img { width: 140px; height: 140px; }
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
                <tr><th>Tanggal Pinjam</th><td>{{ $borrowing->borrow_date?->format('d/m/Y') ?? '—' }}</td></tr>
                <tr><th>Tanggal Kembali</th><td>{{ $borrowing->expected_return_date?->format('d/m/Y') ?? '—' }}</td></tr>
                <tr><th>Jumlah Item</th><td>{{ isset($items) && $items->count() ? $items->count() : ($item ? 1 : 0) }}</td></tr>
                <tr><th>Approved By</th><td>{{ $borrowing->approvedBy?->name ?? '—' }}</td></tr>
                <tr><th>Approved At</th><td>{{ $borrowing->approved_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                @if($borrowing->rejected_at)
                <tr><th>Rejected At</th><td>{{ $borrowing->rejected_at?->format('d/m/Y H:i') }}</td></tr>
                <tr><th>Alasan Penolakan</th><td>{{ $borrowing->rejection_reason ?? '—' }}</td></tr>
                @endif
                <tr><th>Catatan</th><td>{{ $borrowing->approval_notes ?: ($borrowing->notes ?: '—') }}</td></tr>
            </table>

            <h2 class="h6">Daftar Barang</h2>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Sub Kategori</th>
                            <th>Lokasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($items ?? collect()) as $line)
                            @php $inv = $line->inventoryItem; @endphp
                            <tr>
                                <td><code>{{ $inv?->inventory_number ?? '—' }}</code></td>
                                <td>{{ $inv?->name ?? '—' }}</td>
                                <td>{{ $inv?->category?->name ?? '—' }}</td>
                                <td>{{ $inv?->subCategory?->name ?? '—' }}</td>
                                <td class="small">{{ $inv?->full_location ?? '—' }}</td>
                            </tr>
                        @empty
                            @if($item)
                            <tr>
                                <td><code>{{ $item->inventory_number }}</code></td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->category?->name ?? '—' }}</td>
                                <td>{{ $item->subCategory?->name ?? '—' }}</td>
                                <td class="small">{{ $item->full_location ?? '—' }}</td>
                            </tr>
                            @else
                            <tr><td colspan="5" class="text-muted">Tidak ada barang.</td></tr>
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
