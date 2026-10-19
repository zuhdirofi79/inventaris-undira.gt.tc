@php $isPdf = $isPdf ?? false; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Receipt Approval {{ $number }}</title>
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
        table.info th, table.info td { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: left; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        table.info th { width: 35%; background: #f8fafc; color: #475569; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: fixed; }
        table.items th, table.items td { border: 1px solid #cbd5e1; padding: 4px 5px; text-align: left; font-size: 9px; line-height: 1.35; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        table.items th { background: #f8fafc; color: #475569; font-weight: 700; }
        table.items thead { display: table-header-group; }
        .doc-note { margin-top: 16px; font-size: 12px; }
        .doc-note p { margin: 0 0 4px; }
        table.signatures { width: 100%; margin-top: 10pt; border-collapse: collapse; page-break-inside: avoid; page-break-before: auto; }
        table.signatures td { border: 0; padding: 0; vertical-align: top; }
        table.signatures .sign-col { width: 48%; text-align: center; }
        table.signatures .sign-gap { width: 4%; }
        table.signatures .sign-head { font-size: 10pt; font-weight: 700; line-height: 1.25; color: #1F3C88; margin-bottom: 4pt; }
        table.signatures .sign-subhead { font-size: 8pt; font-weight: 600; color: #475569; }
        table.signatures .sign-qr { margin: 4pt auto; width: 110px; height: 110px; }
        table.signatures .sign-qr img { width: 110px; height: 110px; display: block; margin: 0 auto; }
        table.signatures .sign-caption { font-size: 7.5pt; color: #64748b; margin: 2pt 0 4pt; }
        table.signatures .sign-name { font-size: 9pt; line-height: 1.35; word-wrap: break-word; overflow-wrap: break-word; font-weight: 600; }
        table.signatures .sign-decision { font-weight: 500; color: #475569; font-size: 8pt; }
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
        <a href="{{ $pdfUrl }}">Download PDF</a>
        <a href="{{ $detailUrl }}">Halaman Approval</a>
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
    <div><strong>Nomor Approval:</strong> {{ $number }}</div>
    <div style="margin-top:6px;"><span class="badge">{{ $statusLabel }}</span></div>
</div>

<table class="info">
    @foreach($rows as [$label, $value])
        <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
    @endforeach
</table>

<h2 style="margin:16px 0 0;font-size:13px;color:#1F3C88;">{{ $tableTitle }}</h2>
<table class="items">
    <thead>
        <tr>
            @foreach($columns as $column)
                <th>{{ $column }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($lines as $line)
            <tr>
                @foreach($line as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

<div class="doc-note">
    <p>Dokumen ini digenerate otomatis oleh sistem Inventaris UNDIRA.</p>
    <p>{{ $generatedAt }}</p>
</div>

@include('operations.receipts._signature-qr', ['signature' => $signature ?? []])
</body>
</html>
