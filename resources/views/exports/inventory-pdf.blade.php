<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] ?? 'Laporan Inventaris UNDIRA' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        .header { border-bottom: 2px solid #003366; padding-bottom: 8px; margin-bottom: 12px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; border: none; padding: 0; }
        .logo { width: 48px; height: auto; }
        .univ { font-size: 13px; font-weight: bold; color: #003366; margin: 0; }
        .title { font-size: 12px; margin: 2px 0 0; }
        .meta { color: #555; font-size: 9px; margin-top: 3px; }
        .filters { margin: 6px 0 10px; font-size: 9px; }
        .filters span { display: inline-block; margin-right: 8px; }
        .report-h-campus { font-size: 12px; color: #003366; border-bottom: 1px solid #003366; padding-bottom: 2px; margin: 12px 0 5px; page-break-after: avoid; }
        .report-h-building { font-size: 11px; margin: 8px 0 3px; page-break-after: avoid; }
        .report-h-floor { font-size: 10px; margin: 6px 0 2px; page-break-after: avoid; }
        .report-h-room { font-size: 10px; margin: 5px 0 2px; background: #f0f0f0; padding: 2px 5px; page-break-after: avoid; }
        .report-h-category { font-weight: bold; margin-top: 3px; }
        .report-h-sub { margin-left: 6px; color: #444; }
        .report-items { margin: 1px 0 5px 14px; padding: 0; }
        .report-items li { margin-bottom: 1px; }
        .report-meta { color: #555; font-size: 9px; }
        .report-subtotal { font-size: 9px; color: #333; border-top: 1px dashed #bbb; padding-top: 2px; margin: 3px 0 6px; }
        .report-campus-summary { background: #eef5ff; border: 1px solid #c5d8f0; padding: 6px 8px; margin: 8px 0 12px; page-break-inside: avoid; }
        .footer-box { margin-top: 14px; border-top: 2px solid #003366; padding-top: 6px; font-size: 9px; page-break-inside: avoid; }
        .footer-box table { width: 100%; border-collapse: collapse; }
        .footer-box td { padding: 2px 3px; border: none; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/logo-undira.png');
        $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width:58px;">
                    @if($logoData)
                        <img class="logo" src="{{ $logoData }}" alt="UNDIRA">
                    @endif
                </td>
                <td>
                    <p class="univ">Universitas Dian Nusantara</p>
                    <p class="title">{{ $meta['title'] ?? 'Laporan Inventaris' }}</p>
                    <div class="meta">
                        Tanggal cetak: {{ $meta['printed_at'] ?? now()->format('d/m/Y H:i') }}
                        · User: {{ $meta['user_name'] ?? '-' }}
                    </div>
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
                <td>Total Nilai: Rp {{ number_format($summary['total_value'] ?? 0, 0, ',', '.') }}</td>
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
            @foreach(($summary['by_category'] ?? []) as $cat => $count)
                <tr><td colspan="2">{{ $cat }}: {{ number_format($count) }}</td></tr>
            @endforeach
        </table>
    </div>
</body>
</html>
