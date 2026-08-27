<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $meta['title'] ?? 'Laporan Inventaris' }}</title>
    @php
        $orientation = ($meta['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $isLandscape = $orientation === 'landscape';
        // A4 = 210×297mm. Content width must stay inside @page left/right margins.
        $pageSize = $isLandscape ? 'A4 landscape' : 'A4 portrait';
        $pageMargin = $isLandscape ? '8mm 8mm 16mm 8mm' : '10mm 8mm 16mm 8mm';
        $contentWidth = $isLandscape ? '281mm' : '194mm'; // 297-16 or 210-16
        $tableWidth = $isLandscape ? '280mm' : '193mm';
    @endphp
    <style>
        {!! file_get_contents(public_path('css/print.css')) !!}

        @page {
            size: {{ $pageSize }};
            margin: {{ $pageMargin }};
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: auto !important;
            max-width: 100% !important;
        }

        body.rpt-pdf {
            background: #fff;
        }

        .rpt-doc {
            width: {{ $contentWidth }} !important;
            max-width: {{ $contentWidth }} !important;
            margin: 0 !important;
            padding: 0 0 3mm 0 !important;
            border: none !important;
            overflow: visible;
        }

        .rpt-header-table,
        .rpt-filters,
        .rpt-summary,
        .rpt-composition,
        .rpt-table-wrap,
        .rpt-grand {
            width: 100%;
            max-width: {{ $contentWidth }};
        }

        .rpt-table {
            width: {{ $tableWidth }};
            max-width: {{ $tableWidth }};
            table-layout: fixed;
        }

        body.rpt-pdf .rpt-composition-layout {
            width: 100%;
            max-width: 100%;
            border-spacing: 3px 4px;
        }

        /* Portrait: explicit mm columns that sum to 193mm (inside 194mm content box). */
        body.rpt-pdf.rpt-portrait .rpt-table--portrait .rpt-col-no { width: 8mm; }
        body.rpt-pdf.rpt-portrait .rpt-table--portrait .rpt-col-qr { width: 15mm; }
        body.rpt-pdf.rpt-portrait .rpt-table--portrait .rpt-col-inv { width: 34mm; }
        body.rpt-pdf.rpt-portrait .rpt-table--portrait .rpt-col-name { width: 42mm; }
        body.rpt-pdf.rpt-portrait .rpt-table--portrait .rpt-col-loc { width: 52mm; }
        body.rpt-pdf.rpt-portrait .rpt-table--portrait .rpt-col-status { width: 15mm; }
        body.rpt-pdf.rpt-portrait .rpt-table--portrait .rpt-col-cond { width: 14mm; }
        body.rpt-pdf.rpt-portrait .rpt-table--portrait .rpt-col-year { width: 13mm; }

        body.rpt-pdf.rpt-portrait .rpt-table--portrait {
            font-size: 8pt;
        }

        body.rpt-pdf.rpt-portrait .rpt-table--portrait th {
            font-size: 7.5pt;
            padding: 3px 2px;
            line-height: 1.3;
        }

        body.rpt-pdf.rpt-portrait .rpt-table--portrait td {
            padding: 3px 2px;
            font-size: 8pt;
            line-height: 1.35;
        }

        body.rpt-pdf.rpt-portrait .rpt-qr,
        body.rpt-pdf.rpt-portrait .rpt-table--portrait .rpt-qr {
            width: 10mm;
            height: 10mm;
            max-width: 10mm;
            max-height: 10mm;
        }

        .rpt-page-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 8mm;
            border-top: 0.6pt solid #666;
            padding-top: 1.5pt;
            font-size: 7pt;
            line-height: 1.3;
            background: #fff;
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
