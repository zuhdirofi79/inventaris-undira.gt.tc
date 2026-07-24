<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['title'] ?? 'Laporan Inventaris' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/print.css') }}">
    <style>
        body { font-family: Poppins, DejaVu Sans, Arial, sans-serif; }
        @page { size: A4 {{ ($meta['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait' }}; margin: 12mm 10mm 14mm 10mm; }
        .rpt-toolbar { display:flex; gap:8px; margin-bottom:12px; font-family: system-ui,sans-serif; }
        .rpt-toolbar button, .rpt-toolbar a { font-size:13px; padding:6px 12px; border:1px solid #333; background:#fff; color:#111; text-decoration:none; cursor:pointer; }
    </style>
</head>
<body class="rpt-screen">
    <div class="rpt-toolbar no-print">
        <button type="button" onclick="window.print()">Cetak / Print</button>
        <a href="{{ route('reports.show', $meta['type'] ?? 'lengkap') }}">Kembali</a>
    </div>
    <div class="rpt-doc rpt-{{ $meta['orientation'] ?? 'portrait' }}">
        @include('reports._document-header', ['embedLogo' => false])
        @php $type = $type ?? \App\Enums\InventoryReportType::tryFrom($meta['type'] ?? '') ?? \App\Enums\InventoryReportType::Complete; @endphp
        @if($type->isHierarchy())
            @include('reports._hierarchy')
        @else
            @include('reports._flat-table', ['items' => $items])
        @endif
        @include('reports._document-footer')
        <footer class="rpt-page-footer">
            <table class="rpt-page-footer-table">
                <tr>
                    <td class="left">{{ $meta['organization'] ?? 'Universitas Dian Nusantara' }}</td>
                    <td class="center">{{ $meta['system'] ?? 'Sistem Inventaris' }}</td>
                    <td class="right">Halaman dicetak otomatis oleh browser</td>
                </tr>
            </table>
        </footer>
    </div>
    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });</script>
</body>
</html>
