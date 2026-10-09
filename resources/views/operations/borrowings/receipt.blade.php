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
        table.header-bar { width: 100%; border-collapse: collapse; margin-bottom: 16px; border-bottom: 2px solid #1F3C88; }
        table.header-bar td { border: 0; padding: 0 0 10px; vertical-align: middle; }
        .header-side { width: 72px; }
        .header-brand { text-align: center; }
        .header-brand img { height: 56px; margin-bottom: 8px; }
        .header-brand h1 { margin: 0; font-size: 18px; color: #1F3C88; }
        .header-brand p { margin: 4px 0 0; color: #64748b; }
        .header-qr { text-align: right; }
        .header-qr img { width: 64px; height: 64px; }
        .meta { margin: 16px 0; }
        .meta strong { color: #1F3C88; font-size: 14px; }
        table.info { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.info th, table.info td { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: left; vertical-align: top; }
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
        .doc-note { margin-top: 16px; font-size: 12px; }
        .doc-note p { margin: 0 0 4px; }
        table.signatures { width: 100%; margin-top: 14pt; border-collapse: collapse; page-break-inside: avoid; }
        table.signatures td { border: 0; padding: 0; vertical-align: top; }
        table.signatures .sign-col { width: 48%; }
        table.signatures .sign-gap { width: 4%; }
        table.signatures .sign-head { height: 28pt; font-size: 10pt; font-weight: 700; line-height: 1.25; }
        table.signatures .sign-space { height: 76pt; font-size: 10pt; line-height: 76pt; }
        table.signatures .sign-rule { width: 160pt; border-collapse: collapse; }
        table.signatures .sign-rule td { border-bottom: 1px solid #1e293b; font-size: 1pt; line-height: 1pt; }
        table.signatures .sign-name { padding-top: 4pt; font-size: 9pt; line-height: 1.3; }
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

<table class="header-bar">
    <tr>
        <td class="header-side">&nbsp;</td>
        <td class="header-brand">
            @if(!empty($logoDataUri))
                <img src="{{ $logoDataUri }}" alt="Logo UNDIRA">
            @endif
            <h1>Receipt Approval Peminjaman</h1>
            <p>Universitas Dian Nusantara</p>
        </td>
        <td class="header-side header-qr">
            @if(!empty($qrDataUri))
                <img src="{{ $qrDataUri }}" alt="QR Approval">
            @endif
        </td>
    </tr>
</table>

<div class="meta">
    <div><strong>Nomor Approval:</strong> {{ $borrowing->approval_number ?? '—' }}</div>
    <div style="margin-top:6px;"><span class="badge">{{ $borrowing->status?->label() }}</span></div>
</div>

<table class="info">
    <tr><th>Nama Peminjam</th><td>{{ $borrowing->borrower_name }}</td></tr>
    <tr><th>Email</th><td>{{ $borrowing->borrower_email ?? '—' }}</td></tr>
    <tr><th>Tanggal Pinjam</th><td>{{ $borrowing->plannedScheduleText('borrow_date') }}</td></tr>
    <tr><th>Tanggal Kembali</th><td>{{ $borrowing->plannedScheduleText('expected_return_date') }}</td></tr>
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

<div class="doc-note">
    <p>Dokumen ini digenerate otomatis oleh sistem Inventaris UNDIRA.</p>
    <p>{{ now()->format('d/m/Y H:i') }}</p>
</div>

<table class="signatures">
    <tr>
        <td class="sign-col">
            <div class="sign-head">Peminjam,</div>
            <div class="sign-space">&nbsp;</div>
            <table class="sign-rule"><tr><td>&nbsp;</td></tr></table>
            <div class="sign-name">{{ $borrowing->borrower_name }}</div>
        </td>
        <td class="sign-gap">&nbsp;</td>
        <td class="sign-col">
            <div class="sign-head">Biro PPPA<br>Universitas Dian Nusantara</div>
            <div class="sign-space">&nbsp;</div>
            <table class="sign-rule"><tr><td>&nbsp;</td></tr></table>
            <div class="sign-name">(Nama Petugas Biro PPPA)</div>
        </td>
    </tr>
</table>

@if($autoPrint)
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
@endif
</body>
</html>
