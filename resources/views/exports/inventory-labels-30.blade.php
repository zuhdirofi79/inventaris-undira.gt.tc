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
            padding: 0.8mm 1.2mm;
            overflow: hidden;
            text-align: center;
        }

        .label-empty {
            border: 0.4pt dashed #e8edf3;
        }

        table.stack {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td.stack-logo {
            height: {{ $layout['logo_max_height_mm'] + 0.8 }}mm;
            vertical-align: middle;
            text-align: center;
            padding: 0 0 0.4mm;
        }

        td.stack-qr {
            height: {{ $layout['qr_size_mm'] + 0.6 }}mm;
            vertical-align: middle;
            text-align: center;
            padding: 0.2mm 0;
        }

        td.stack-number {
            vertical-align: middle;
            text-align: center;
            padding: 0.3mm 0 0.2mm;
        }

        td.stack-name {
            vertical-align: top;
            text-align: center;
            padding: 0;
        }

        img.logo {
            width: {{ $layout['logo_width_mm'] }}mm;
            max-width: {{ $layout['logo_width_mm'] }}mm;
            max-height: {{ $layout['logo_max_height_mm'] }}mm;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        img.qr {
            width: {{ $layout['qr_size_mm'] }}mm;
            height: {{ $layout['qr_size_mm'] }}mm;
            max-width: {{ $layout['qr_size_mm'] }}mm;
            max-height: {{ $layout['qr_size_mm'] }}mm;
            display: block;
            margin: 0 auto;
        }

        .number {
            font-size: {{ $layout['font_size_pt'] }}pt;
            color: #b91c1c;
            font-weight: bold;
            line-height: 1.15;
            word-break: break-all;
            overflow: hidden;
            max-height: {{ $layout['font_size_pt'] * 2.2 }}pt;
        }

        .name {
            font-size: {{ max(7, $layout['font_size_pt'] - 0.3) }}pt;
            font-weight: normal;
            line-height: 1.15;
            text-transform: uppercase;
            max-height: {{ max(7, $layout['font_size_pt'] - 0.3) * 2.35 }}pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        .logo-missing {
            font-size: 5pt;
            color: #b91c1c;
            line-height: 1.15;
            text-align: left;
            word-break: break-all;
            overflow: hidden;
            max-height: {{ $layout['logo_max_height_mm'] + 0.5 }}mm;
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
                        <table class="stack">
                            <tr>
                                <td class="stack-logo">
                                    @if(!empty($logoSrc))
                                        <img class="logo" src="{{ $logoSrc }}" alt="Universitas Dian Nusantara">
                                    @else
                                        <div class="logo-missing">
                                            LOGO GAGAL<br>
                                            {{ \Illuminate\Support\Str::limit($logoMissingMessage ?: 'logo-undira.png tidak ditemukan', 120, '...') }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="stack-qr">
                                    <img class="qr" src="{{ $label->qr_src }}" alt="QR">
                                </td>
                            </tr>
                            <tr>
                                <td class="stack-number">
                                    <div class="number">{{ $label->inventory_number }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="stack-name">
                                    <div class="name">{{ $label->name }}</div>
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
