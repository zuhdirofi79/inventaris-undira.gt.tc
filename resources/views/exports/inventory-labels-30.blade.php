<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Label QR Inventaris</title>
    <style>
        /*
         * DomPDF-stable mass labels — A4 portrait, 5×8 = 40 / page.
         *
         * IMPORTANT: do NOT use border-spacing / flex / min-height:100vh.
         * DomPDF mis-measures border-spacing and page-breaks early → large
         * empty bands in the middle of the sheet.
         *
         * Gaps = equal cell padding; grid exactly fills the usable page area.
         */
        @page {
            size: A4 portrait;
            margin: {{ $layout['page_margin_mm'] }}mm;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #111827;
            background: #fff;
        }

        .page {
            width: 100%;
            page-break-after: always;
            page-break-inside: avoid;
        }

        .page:last-child {
            page-break-after: auto;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
            margin: 0;
            padding: 0;
        }

        table.grid tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        td.cell {
            width: {{ $layout['cell_width_pct'] }}%;
            height: {{ $layout['cell_height_mm'] }}mm;
            max-height: {{ $layout['cell_height_mm'] }}mm;
            padding: {{ $layout['cell_pad_y_mm'] }}mm {{ $layout['cell_pad_x_mm'] }}mm;
            vertical-align: top;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .label {
            width: 100%;
            height: {{ $layout['label_height_mm'] }}mm;
            max-height: {{ $layout['label_height_mm'] }}mm;
            border: 1px solid #D1D5DB;
            border-radius: 1mm;
            background: #fff;
            padding: {{ $layout['label_padding_mm'] }}mm {{ max(0.8, $layout['label_padding_mm'] - 0.1) }}mm {{ $layout['label_padding_bottom_mm'] }}mm {{ max(0.8, $layout['label_padding_mm'] - 0.1) }}mm;
            overflow: hidden;
        }

        .label-empty {
            border: 0.35pt solid #F3F4F6;
            background: #fff;
        }

        table.label-inner {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
        }

        td.col-text {
            width: {{ max(40, 100 - $layout['qr_col_width_pct']) }}%;
            vertical-align: top;
            padding: 0 0.3mm 0 0;
            overflow: hidden;
        }

        td.col-qr {
            width: {{ $layout['qr_col_width_pct'] }}%;
            vertical-align: top;
            text-align: right;
            padding: 0;
        }

        .logo-wrap {
            height: {{ $layout['logo_max_height_mm'] + 0.15 }}mm;
            margin-bottom: 0.35mm;
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
            line-height: 1.1;
            text-align: left;
            word-break: break-all;
        }

        .name {
            font-size: 8.5pt;
            font-weight: bold;
            line-height: 1.1;
            color: #111827;
            margin: 0 0 0.25mm 0;
            max-height: 9.6pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        .category {
            font-size: 7pt;
            font-weight: normal;
            line-height: 1.1;
            color: #111827;
            margin: 0 0 0.2mm 0;
            max-height: 7.8pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        .room {
            font-size: 7pt;
            font-weight: bold;
            line-height: 1.1;
            color: #111827;
            margin: 0 0 0.4mm 0;
            max-height: 7.8pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        .number-rule {
            border: 0;
            border-top: 0.5pt solid #D1D5DB;
            margin: 0 0 0.25mm 0;
            height: 0;
        }

        .number {
            font-weight: bold;
            line-height: 1.05;
            letter-spacing: -0.03em;
            color: #111827;
            text-align: left;
            white-space: nowrap;
            overflow: visible;
            word-break: keep-all;
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
    $chunkCount = $chunks->count();
    $chunkIndex = 0;
@endphp

@foreach($chunks as $pageLabels)
@php
    $chunkIndex++;
    $isLastPage = $chunkIndex === $chunkCount;
    $cells = $pageLabels->values()->all();
    // Always fill the full 5×8 grid so the sheet stays dense (empty slots only on last page).
    while (count($cells) < $perPage) {
        $cells[] = null;
    }
    $rows = array_chunk($cells, $columns);
@endphp
<div class="page"@if($isLastPage) style="page-break-after:auto;"@endif>
    <table class="grid">
        @foreach($rows as $row)
        <tr>
            @foreach($row as $label)
            <td class="cell">
                @if($label)
                    @php
                        $invNo = (string) $label->inventory_number;
                        $invLen = mb_strlen($invNo);
                        $invPt = $invLen >= 30 ? 5.2 : ($invLen >= 26 ? 5.5 : ($invLen >= 22 ? 5.8 : 6.1));
                    @endphp
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
                                    <div class="number" style="font-size: {{ $invPt }}pt;">{{ $invNo }}</div>
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
