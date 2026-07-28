@php
    $isPdf = $isPdf ?? false;
    $autoPrint = $autoPrint ?? false;
    $item = $item ?? $borrowing->inventoryItem;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Receipt Approval {{ $borrowing->approval_number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1e293b; font-size: 12px; margin: 24px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1F3C88; padding-bottom: 12px; }
        .header img { height: 56px; margin-bottom: 8px; }
        .header h1 { margin: 0; font-size: 18px; color: #1F3C88; }
        .header p { margin: 4px 0 0; color: #64748b; }
        .meta { margin: 16px 0; }
        .meta strong { color: #1F3C88; font-size: 14px; }
        table.info { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.info th, table.info td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; vertical-align: top; }
        table.info th { width: 35%; background: #f8fafc; color: #475569; }
        .footer { margin-top: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
        .qr { text-align: center; }
        .qr img { width: 120px; height: 120px; }
        .qr small { display: block; margin-top: 6px; color: #64748b; word-break: break-all; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; background: #E8F5E9; color: #2E7D32; font-weight: 700; }
        .actions { margin-bottom: 16px; }
        .actions a, .actions button { margin-right: 8px; }
        @media print { .no-print { display: none !important; } body { margin: 0; } }
    </style>
</head>
<body>
@unless($isPdf)
<div class="actions no-print">
    <button type="button" onclick="window.print()">Cetak</button>
    @if(!empty($borrowing->approval_number))
        <a href="{{ route('borrowing.approval.receipt.pdf', $borrowing->approval_number) }}">Download PDF</a>
        <a href="{{ route('borrowing.approval.public', $borrowing->approval_number) }}">Halaman Approval</a>
    @endif
</div>
@endunless

<div class="header">
    @if(!empty($logoDataUri))
        <img src="{{ $logoDataUri }}" alt="Logo UNDIRA">
    @endif
    <h1>Receipt Approval Peminjaman</h1>
    <p>Universitas Dian Nusantara</p>
</div>

<div class="meta">
    <div><strong>Nomor Approval:</strong> {{ $borrowing->approval_number ?? '—' }}</div>
    <div style="margin-top:6px;"><span class="badge">{{ $borrowing->status?->label() }}</span></div>
</div>

<table class="info">
    <tr><th>Nama Peminjam</th><td>{{ $borrowing->borrower_name }}</td></tr>
    <tr><th>Email</th><td>{{ $borrowing->borrower_email ?? '—' }}</td></tr>
    <tr><th>Nomor Inventaris</th><td>{{ $item?->inventory_number ?? '—' }}</td></tr>
    <tr><th>Nama Barang</th><td>{{ $item?->name ?? '—' }}</td></tr>
    <tr><th>Kategori</th><td>{{ $item?->category?->name ?? '—' }}</td></tr>
    <tr><th>Subkategori</th><td>{{ $item?->subCategory?->name ?? '—' }}</td></tr>
    <tr><th>Lokasi</th><td>{{ $item?->full_location ?? $item?->locationDisplayName() ?? '—' }}</td></tr>
    <tr><th>Tanggal Pinjam</th><td>{{ $borrowing->borrow_date?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><th>Tanggal Kembali</th><td>{{ $borrowing->expected_return_date?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><th>Disetujui Oleh</th><td>{{ $borrowing->approvedBy?->name ?? '—' }}</td></tr>
    <tr><th>Tanggal Persetujuan</th><td>{{ $borrowing->approved_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><th>Catatan</th><td>{{ $borrowing->approval_notes ?: ($borrowing->notes ?: '—') }}</td></tr>
</table>

<div class="footer">
    <div>
        <p>Dokumen ini digenerate otomatis oleh sistem Inventaris UNDIRA.</p>
        <p>{{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <div class="qr">
        @if(!empty($qrDataUri))
            <img src="{{ $qrDataUri }}" alt="QR Approval">
            <small>Scan untuk verifikasi</small>
        @endif
    </div>
</div>

@if($autoPrint)
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
@endif
</body>
</html>
