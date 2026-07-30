<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Label QR Inventaris</title>
    <style>
        @page {
            size: A4 portrait;
            margin: {{ $layout['page_margin_mm'] }}mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            background: #fff;
        }

        .page {
            width: 100%;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        table.grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: {{ $layout['gap_x_mm'] }}mm {{ $layout['gap_y_mm'] }}mm;
            table-layout: fixed;
        }

        td.cell {
            width: {{ $layout['cell_width_pct'] }}%;
            height: {{ $layout['cell_height_mm'] }}mm;
            vertical-align: top;
            padding: 0;
        }

        .label {
            width: 100%;
            height: {{ $layout['cell_height_mm'] }}mm;
            border: 0.5pt solid #c5cdd8;
            border-radius: 1.2mm;
            background: #fff;
            padding: 1mm 1.3mm;
            overflow: hidden;
        }

        .label-empty {
            border: 0.4pt dashed #e8edf3;
        }

        table.shell {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td.header {
            height: {{ $layout['logo_max_height_mm'] + 1.2 }}mm;
            vertical-align: middle;
            padding: 0 0 0.6mm;
            border-bottom: 0.4pt solid #e2e8f0;
        }

        table.header-row {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td.logo-cell {
            width: {{ $layout['logo_width_mm'] + 1 }}mm;
            vertical-align: middle;
            padding: 0;
        }

        td.number-cell {
            vertical-align: middle;
            text-align: right;
            padding: 0 0 0 1mm;
        }

        img.logo {
            width: {{ $layout['logo_width_mm'] }}mm;
            max-width: {{ $layout['logo_width_mm'] }}mm;
            max-height: {{ $layout['logo_max_height_mm'] }}mm;
            height: auto;
            display: block;
        }

        .number {
            font-size: {{ $layout['font_size_pt'] }}pt;
            color: #b91c1c;
            font-weight: bold;
            line-height: 1.1;
            word-break: break-all;
            overflow: hidden;
            max-height: {{ $layout['font_size_pt'] * 2.2 }}pt;
        }

        td.body {
            vertical-align: middle;
            padding: 0.8mm 0 0;
        }

        table.body-row {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td.qr-col {
            width: {{ $layout['qr_size_mm'] + 1.5 }}mm;
            text-align: center;
            vertical-align: middle;
            padding: 0;
        }

        td.info-col {
            vertical-align: middle;
            padding: 0 0 0 1.4mm;
            text-align: left;
        }

        img.qr {
            width: {{ $layout['qr_size_mm'] }}mm;
            height: {{ $layout['qr_size_mm'] }}mm;
            max-width: {{ $layout['qr_size_mm'] }}mm;
            max-height: {{ $layout['qr_size_mm'] }}mm;
            display: block;
            margin: 0 auto;
        }

        .name {
            font-size: {{ $layout['font_size_pt'] }}pt;
            font-weight: bold;
            line-height: 1.15;
            text-transform: uppercase;
            max-height: {{ $layout['font_size_pt'] * 2.35 }}pt;
            overflow: hidden;
            margin: 0 0 0.7mm;
            word-wrap: break-word;
        }

        .loc {
            font-size: {{ max(7, $layout['font_size_pt'] - 0.5) }}pt;
            color: #334155;
            font-weight: bold;
            line-height: 1.2;
            overflow: hidden;
            max-height: {{ max(7, $layout['font_size_pt'] - 0.5) * 3.7 }}pt;
        }

        .loc-line {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logo-fallback {
            font-size: 6pt;
            font-weight: bold;
            color: #1e3a5f;
            line-height: 1.1;
        }
    </style>
</head>
<body>
@php
    /** @var \Illuminate\Support\Collection $labels */
    /** @var array $layout */
    $perPage = (int) $layout['per_page'];
    $columns = (int) $layout['columns'];
    $chunks = $labels->values()->chunk($perPage);
    if ($chunks->isEmpty()) {
        $chunks = collect([collect()]);
    }
@endphp

@foreach($chunks as $pageLabels)
@php
    $cells = $pageLabels->values()->all();
    while (count($cells) < $perPage) {
        $cells[] = null;
    }
    $rows = array_chunk($cells, $columns);
@endphp
<div class="page">
    <table class="grid">
        @foreach($rows as $row)
        <tr>
            @foreach($row as $label)
            <td class="cell">
                @if($label)
                    <div class="label">
                        <table class="shell">
                            <tr>
                                <td class="header">
                                    <table class="header-row">
                                        <tr>
                                            <td class="logo-cell">
                                                @if(!empty($logoSrc))
                                                    <img class="logo" src="{{ $logoSrc }}" alt="Universitas Dian Nusantara">
                                                @else
                                                    <div class="logo-fallback">UNDIRA</div>
                                                @endif
                                            </td>
                                            <td class="number-cell">
                                                <div class="number">{{ $label->inventory_number }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td class="body">
                                    <table class="body-row">
                                        <tr>
                                            <td class="qr-col">
                                                <img class="qr" src="{{ $label->qr_src }}" alt="QR">
                                            </td>
                                            <td class="info-col">
                                                <div class="name">{{ $label->name }}</div>
                                                <div class="loc">
                                                    @foreach($label->location_lines as $line)
                                                        <span class="loc-line">{{ $line }}</span>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </div>
                @else
                    <div class="label label-empty">&nbsp;</div>
                @endif
            </td>
            @endforeach
        </tr>
        @endforeach
    </table>
</div>
@endforeach
</body>
</html>
