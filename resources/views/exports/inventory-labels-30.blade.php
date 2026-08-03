<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Label QR Inventaris</title>
    <style>
        /*
         * Mass QR labels — A4 portrait, horizontal layout.
         * DomPDF-safe: tables only (no flex/grid).
         * Left: logo + name + category + room + number
         * Right: large QR (full label height, primary focus)
         */
        @page {
            size: A4 portrait;
            margin: {{ $layout['page_margin_mm'] }}mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #111827;
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
            border: 1px solid #D1D5DB;
            border-radius: 1mm;
            background: #fff;
            padding: {{ $layout['label_padding_mm'] }}mm;
            overflow: hidden;
        }

        .label-empty {
            border: 0.4pt dashed #E5E7EB;
            background: #fff;
        }

        table.label-inner {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td.col-text {
            width: {{ max(42, 100 - $layout['qr_col_width_pct']) }}%;
            vertical-align: top;
            padding: 0 {{ max(0.8, $layout['label_padding_mm'] * 0.35) }}mm 0 0;
            overflow: hidden;
        }

        td.col-qr {
            width: {{ $layout['qr_col_width_pct'] }}%;
            vertical-align: middle;
            text-align: right;
            padding: 0;
        }

        .logo-wrap {
            height: {{ $layout['logo_max_height_mm'] + 0.3 }}mm;
            margin-bottom: 0.55mm;
            overflow: hidden;
            text-align: left;
        }

        img.logo {
            width: {{ $layout['logo_width_mm'] }}mm;
            max-width: {{ $layout['logo_width_mm'] }}mm;
            max-height: {{ $layout['logo_max_height_mm'] }}mm;
            height: auto;
            display: block;
        }

        .logo-missing {
            font-size: 5pt;
            color: #B91C1C;
            line-height: 1.15;
            text-align: left;
            word-break: break-all;
        }

        .name {
            font-size: 9pt;
            font-weight: bold;
            line-height: 1.15;
            color: #111827;
            margin: 0 0 0.45mm 0;
            max-height: 11pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        .category {
            font-size: 7pt;
            font-weight: normal;
            line-height: 1.15;
            color: #111827;
            margin: 0 0 0.35mm 0;
            max-height: 8.2pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        .room {
            font-size: 7pt;
            font-weight: bold;
            line-height: 1.15;
            color: #111827;
            margin: 0 0 0.7mm 0;
            max-height: 8.2pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        .number-rule {
            border: 0;
            border-top: 0.5pt solid #D1D5DB;
            margin: 0 0 0.4mm 0;
            height: 0;
        }

        .number {
            font-size: 7.5pt;
            font-weight: bold;
            line-height: 1.15;
            color: #111827;
            text-align: left;
            word-break: break-all;
            overflow: hidden;
            max-height: 6.2mm;
        }

        .qr-wrap {
            display: inline-block;
            width: {{ $layout['qr_size_mm'] }}mm;
            height: {{ $layout['qr_size_mm'] }}mm;
            background: #fff;
            text-align: center;
        }

        img.qr {
            width: {{ $layout['qr_size_mm'] }}mm;
            height: {{ $layout['qr_size_mm'] }}mm;
            max-width: {{ $layout['qr_size_mm'] }}mm;
            max-height: {{ $layout['qr_size_mm'] }}mm;
            display: block;
        }

        @media print {
            html, body { background: #fff; }
            .page { page-break-after: always; }
            .page:last-child { page-break-after: auto; }
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
    $logoMissingMessage = $logoError ?? null;
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
                        <table class="label-inner">
                            <tr>
                                <td class="col-text">
                                    <div class="logo-wrap">
                                        @if(!empty($logoSrc))
                                            <img class="logo" src="{{ $logoSrc }}" alt="Universitas Dian Nusantara">
                                        @else
                                            <div class="logo-missing">
                                                LOGO GAGAL<br>
                                                {{ \Illuminate\Support\Str::limit($logoMissingMessage ?: 'logo-undira.png tidak ditemukan', 80, '...') }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="name">{{ $label->name }}</div>
                                    <div class="category">{{ $label->category }}</div>
                                    <div class="room">{{ $label->room }}</div>
                                    <div class="number-rule"></div>
                                    <div class="number">{{ $label->inventory_number }}</div>
                                </td>
                                <td class="col-qr">
                                    <div class="qr-wrap">
                                        <img class="qr" src="{{ $label->qr_src }}" alt="QR">
                                    </div>
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
