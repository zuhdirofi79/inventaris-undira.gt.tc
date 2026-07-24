<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $meta['title'] ?? 'Laporan Inventaris' }}</title>
    <style>
        {!! file_get_contents(public_path('css/print.css')) !!}
        @page { margin: 12mm 10mm 16mm 10mm; }
        .rpt-page-footer { position: fixed; left: 0; right: 0; bottom: -10mm; height: 10mm; border-top: 0.6pt solid #666; padding-top: 2px; font-size: 8pt; }
        .rpt-pagenum:after { content: counter(page); }
        .rpt-pagetotal:before { content: counter(pages); }
    </style>
</head>
<body class="rpt-{{ $meta['orientation'] ?? 'portrait' }}">
    <div class="rpt-doc rpt-{{ $meta['orientation'] ?? 'portrait' }}">
        @include('reports._document-header', ['embedLogo' => true])
        @php
            $type = $type ?? \App\Enums\InventoryReportType::tryFrom($meta['type'] ?? '') ?? \App\Enums\InventoryReportType::Complete;
        @endphp
        @if($type->isHierarchy())
            @include('reports._hierarchy')
        @else
            @include('reports._flat-table', ['items' => $items])
        @endif
        @include('reports._document-footer')
    </div>
    <footer class="rpt-page-footer">
        <table class="rpt-page-footer-table">
            <tr>
                <td class="left">{{ $meta['organization'] ?? 'Universitas Dian Nusantara' }}</td>
                <td class="center">{{ $meta['system'] ?? 'Sistem Inventaris' }}</td>
                <td class="right">Halaman <span class="rpt-pagenum"></span> / <span class="rpt-pagetotal"></span></td>
            </tr>
        </table>
    </footer>
</body>
</html>
