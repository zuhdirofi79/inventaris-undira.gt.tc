<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $meta['title'] ?? 'Laporan Inventaris' }}</title>
    @php
        $orientation = ($meta['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $pageMargin = $orientation === 'landscape' ? '8mm' : '10mm';
        $bottomMargin = $orientation === 'landscape' ? '12mm' : '14mm';
    @endphp
    <style>
        {!! file_get_contents(public_path('css/print.css')) !!}

        /* Margins only — paper size/orientation come from DomPDF setPaper() */
        @page {
            margin: {{ $pageMargin }} {{ $pageMargin }} {{ $bottomMargin }} {{ $pageMargin }};
        }

        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        body.rpt-pdf {
            background: #fff;
        }

        .rpt-doc {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0 0 8mm 0;
            border: none;
        }

        .rpt-page-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -6mm;
            height: 9mm;
            border-top: 0.6pt solid #666;
            padding-top: 2px;
            font-size: 8px;
        }

        .rpt-pagenum:after { content: counter(page); }
        .rpt-pagetotal:before { content: counter(pages); }
    </style>
</head>
<body class="rpt-pdf rpt-{{ $orientation }}">
    <div class="rpt-doc rpt-{{ $orientation }}">
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
                <td class="left">Dicetak oleh {{ $meta['system'] ?? 'Sistem Inventaris' }}</td>
                <td class="center">{{ $meta['printed_date'] ?? '' }} {{ $meta['printed_time'] ?? '' }}</td>
                <td class="right">Halaman <span class="rpt-pagenum"></span> / <span class="rpt-pagetotal"></span></td>
            </tr>
        </table>
    </footer>
</body>
</html>
