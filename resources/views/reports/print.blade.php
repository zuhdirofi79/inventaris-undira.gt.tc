<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 24px; }
        .header { border-bottom: 2px solid #003366; padding-bottom: 10px; margin-bottom: 14px; }
        .header-table { width: 100%; }
        .header-table td { vertical-align: middle; }
        .logo { width: 56px; height: auto; }
        .univ { font-size: 14px; font-weight: bold; color: #003366; margin: 0; }
        .title { font-size: 13px; margin: 2px 0 0; }
        .meta { color: #555; font-size: 10px; margin-top: 4px; }
        .filters { margin: 8px 0 14px; font-size: 10px; }
        .filters span { display: inline-block; margin-right: 10px; }
        .report-h-campus { font-size: 13px; color: #003366; border-bottom: 1px solid #003366; padding-bottom: 3px; margin: 14px 0 6px; }
        .report-h-building { font-size: 12px; margin: 10px 0 4px; }
        .report-h-floor { font-size: 11px; margin: 8px 0 3px; }
        .report-h-room { font-size: 11px; margin: 6px 0 3px; background: #f3f3f3; padding: 3px 6px; }
        .report-h-category { font-weight: bold; margin-top: 4px; }
        .report-h-sub { margin-left: 8px; color: #444; }
        .report-items { margin: 2px 0 6px 18px; padding: 0; }
        .report-items li { margin-bottom: 1px; }
        .report-meta { color: #555; font-size: 10px; }
        .report-subtotal { font-size: 10px; color: #333; border-top: 1px dashed #bbb; padding-top: 3px; margin: 4px 0 8px; }
        .report-campus-summary { background: #eef5ff; border: 1px solid #c5d8f0; padding: 8px 10px; margin: 10px 0 14px; }
        .footer-box { margin-top: 18px; border-top: 2px solid #003366; padding-top: 8px; font-size: 10px; }
        .footer-box table { width: 100%; border-collapse: collapse; }
        .footer-box td { padding: 2px 4px; }
        @media print {
            body { margin: 12mm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:12px;">
        <button onclick="window.print()">Cetak</button>
        <a href="{{ route('reports.index') }}">Kembali</a>
    </div>

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width:70px;">
                    <img class="logo" src="{{ asset('images/logo-undira.png') }}" alt="UNDIRA">
                </td>
                <td>
                    <p class="univ">Universitas Dian Nusantara</p>
                    <p class="title">{{ $meta['title'] }}</p>
                    <div class="meta">Tanggal cetak: {{ $meta['printed_at'] }} · User: {{ $meta['user_name'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if(!empty($filters))
    <div class="filters">
        <strong>Filter:</strong>
        @foreach($filters as $label => $value)
            <span>{{ $label }}: {{ $value }}</span>
        @endforeach
    </div>
    @endif

    @include('reports._hierarchy', ['tree' => $tree, 'isPdf' => true])

    <div class="footer-box">
        <strong>Ringkasan Grand Total</strong>
        <table>
            <tr>
                <td>Total Inventaris: {{ number_format($summary['total_items'] ?? 0) }}</td>
                <td>Total Inventaris: {{ number_format($summary['total_items'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Furniture: {{ $summary['category_named']['Furniture'] ?? 0 }}</td>
                <td>Elektronik: {{ $summary['category_named']['Elektronik'] ?? 0 }}</td>
            </tr>
            <tr>
                <td>ATK: {{ $summary['category_named']['ATK'] ?? 0 }}</td>
                <td>Laboratorium: {{ $summary['category_named']['Laboratorium'] ?? 0 }}</td>
            </tr>
            <tr>
                <td>Aktif: {{ $summary['aktif'] ?? 0 }} · Dipinjam: {{ $summary['dipinjam'] ?? 0 }}</td>
                <td>Maintenance: {{ $summary['maintenance'] ?? 0 }} · Rusak: {{ $summary['rusak'] ?? 0 }}</td>
            </tr>
        </table>
    </div>

    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
