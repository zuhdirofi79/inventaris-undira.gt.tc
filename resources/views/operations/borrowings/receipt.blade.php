@php
    $isPdf = $isPdf ?? false;
    $autoPrint = $autoPrint ?? false;
    $item = $item ?? $borrowing->inventoryItem;
    $items = $items ?? $borrowing->items;
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
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: fixed; }
        table.items th, table.items td { border: 1px solid #cbd5e1; padding: 4px 5px; text-align: left; font-size: 9px; line-height: 1.35; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        table.items th { background: #f8fafc; color: #475569; font-weight: 700; }
        table.items thead { display: table-header-group; }
        table.items .c-no { width: 5%; }
        table.items .c-number { width: 22%; }
        table.items .c-name { width: 15%; }
        table.items .c-cat { width: 12%; }
        table.items .c-sub { width: 11%; }
        table.items .c-loc { width: 22%; }
        table.items .c-status { width: 13%; }
        .summary { margin-top: 14px; font-size: 11px; }
        .st-ok, .st-no, .st-wait, .st-unknown { font-weight: 700; }
        .st-ok { color: #166534; }
        .st-no { color: #991b1b; }
        .st-wait { color: #92400e; }
        .st-unknown { color: #334155; }
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
    <tr><th>Tanggal Pinjam</th><td>{{ $borrowing->borrow_date?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><th>Tanggal Kembali</th><td>{{ $borrowing->expected_return_date?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><th>Jumlah Item</th><td>{{ $items?->count() ?: ($item ? 1 : 0) }}</td></tr>
    <tr><th>Disetujui Oleh</th><td>{{ $borrowing->approvedBy?->name ?? '—' }}</td></tr>
    <tr><th>Tanggal Persetujuan</th><td>{{ $borrowing->approved_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><th>Keperluan</th><td>{{ $borrowing->notes ?: '—' }}</td></tr>
    <tr><th>Catatan Approval</th><td>{{ $borrowing->approval_notes ?: '—' }}</td></tr>
</table>

@php
    use App\Enums\BorrowingItemStatus;
    $receiptLines = $items && $items->count() ? $items : collect();
    $approvalOf = function ($line): array {
        return match ($line->status) {
            BorrowingItemStatus::MenungguPersetujuan => ['Menunggu', 'st-wait'],
            BorrowingItemStatus::Disetujui,
            BorrowingItemStatus::Dipinjam,
            BorrowingItemStatus::Dikembalikan => ['Disetujui', 'st-ok'],
            BorrowingItemStatus::Ditolak => ['Ditolak', 'st-no'],
            default => ['Tidak Tersedia', 'st-unknown'],
        };
    };
    $approvalCounts = ['Disetujui' => 0, 'Ditolak' => 0, 'Menunggu' => 0, 'Tidak Tersedia' => 0];
    foreach ($receiptLines as $line) {
        $approvalCounts[$approvalOf($line)[0]]++;
    }
@endphp
@if($receiptLines->isNotEmpty())
<p class="summary">
    <strong>Ringkasan Persetujuan.</strong>
    Jumlah Item: {{ $receiptLines->count() }}.
    Disetujui: {{ $approvalCounts['Disetujui'] }}.
    Ditolak: {{ $approvalCounts['Ditolak'] }}.
    @if($approvalCounts['Menunggu'] > 0)
        Menunggu: {{ $approvalCounts['Menunggu'] }}.
    @endif
    @if($approvalCounts['Tidak Tersedia'] > 0)
        Tidak Tersedia: {{ $approvalCounts['Tidak Tersedia'] }}.
    @endif
</p>
<table class="items">
    <colgroup>
        <col class="c-no">
        <col class="c-number">
        <col class="c-name">
        <col class="c-cat">
        <col class="c-sub">
        <col class="c-loc">
        <col class="c-status">
    </colgroup>
    <thead>
        <tr>
            <th class="c-no">No</th>
            <th class="c-number">Nomor Inventaris</th>
            <th class="c-name">Nama Barang</th>
            <th class="c-cat">Kategori</th>
            <th class="c-sub">Sub Kategori</th>
            <th class="c-loc">Lokasi</th>
            <th class="c-status">Status Persetujuan</th>
        </tr>
    </thead>
    <tbody>
        @foreach($receiptLines as $i => $line)
            @php
                $inv = $line->inventoryItem;
                [$approvalLabel, $approvalClass] = $approvalOf($line);
            @endphp
            <tr>
                <td class="c-no">{{ $i + 1 }}</td>
                <td class="c-number">{{ $inv?->inventory_number ?? '—' }}</td>
                <td class="c-name">{{ $inv?->name ?? '—' }}</td>
                <td class="c-cat">{{ $inv?->category?->name ?? '—' }}</td>
                <td class="c-sub">{{ $inv?->subCategory?->name ?? '—' }}</td>
                <td class="c-loc">{{ $inv?->full_location ?? $inv?->locationDisplayName() ?? '—' }}</td>
                <td class="c-status {{ $approvalClass }}">{{ $approvalLabel }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@elseif($item)
<table class="info" style="margin-top:16px">
    <tr><th>Nomor Inventaris</th><td>{{ $item->inventory_number ?? '—' }}</td></tr>
    <tr><th>Nama Barang</th><td>{{ $item->name ?? '—' }}</td></tr>
    <tr><th>Kategori</th><td>{{ $item->category?->name ?? '—' }}</td></tr>
    <tr><th>Subkategori</th><td>{{ $item->subCategory?->name ?? '—' }}</td></tr>
    <tr><th>Lokasi</th><td>{{ $item->full_location ?? $item->locationDisplayName() ?? '—' }}</td></tr>
    <tr><th>Status Persetujuan</th><td>Tidak Tersedia</td></tr>
</table>
@endif

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
