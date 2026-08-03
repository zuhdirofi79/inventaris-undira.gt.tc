<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Label QR Inventaris</title>
    <style>
        /*
         * DomPDF mass labels — A4 portrait, 5×8 = 40 / page.
         *
         * CRITICAL (empty-page bug):
         * - NEVER put page-break-inside:avoid on .page (full-sheet wrapper).
         *   DomPDF cannot keep a full A4 block on one page → pushes content
         *   and leaves blank sheets between chunks.
         * - NEVER use border-spacing / height:100% / 100vh / min-height:297mm.
         * - Gaps = cell padding only; grid height ≤ usable area (−slack).
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

        /* Only force a break AFTER a full chunk — never "avoid" inside the page. */
        .page {
            width: 100%;
            page-break-after: always;
            page-break-inside: auto;
            break-after: page;
            break-inside: auto;
        }

        .page:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
            margin: 0;
            padding: 0;
        }

        /* Keep a single row together; do not block whole-page flow. */
        table.grid tr {
            page-break-inside: avoid;
            page-break-after: auto;
            break-inside: avoid;
        }

        td.cell {
            width: {{ $layout['cell_width_pct'] }}%;
            height: {{ $layout['cell_height_mm'] }}mm;
            max-height: {{ $layout['cell_height_mm'] }}mm;
            padding: {{ $layout['cell_pad_y_mm'] }}mm {{ $layout['cell_pad_x_mm'] }}mm;
            vertical-align: middle;
            page-break-inside: avoid;
            break-inside: avoid;
            overflow: hidden;
        }

        .label {
            width: 100%;
            height: {{ $layout['label_height_mm'] }}mm;
            max-height: {{ $layout['label_height_mm'] }}mm;
            border: 0.5pt solid #D1D5DB;
            border-radius: 0.8mm;
            background: #fff;
            padding: {{ $layout['label_padding_mm'] }}mm {{ max(0.7, $layout['label_padding_mm'] - 0.05) }}mm {{ $layout['label_padding_bottom_mm'] }}mm {{ max(0.7, $layout['label_padding_mm'] - 0.05) }}mm;
            overflow: hidden;
        }

        .label-empty {
            border: 0.25pt solid #F3F4F6;
            background: #fff;
        }

        table.label-inner {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
        }

        td.col-text {
            width: {{ max(42, 100 - $layout['qr_col_width_pct']) }}%;
            vertical-align: middle;
            padding: 0 0.4mm 0 0;
            overflow: hidden;
        }

        td.col-qr {
            width: {{ $layout['qr_col_width_pct'] }}%;
            vertical-align: middle;
            text-align: right;
            padding: 0;
        }

        .logo-wrap {
            height: {{ $layout['logo_max_height_mm'] }}mm;
            margin: 0 0 0.2mm 0;
            overflow: hidden;
            text-align: left;
            line-height: 1;
        }

        img.logo {
            width: {{ $layout['logo_width_mm'] }}mm;
            max-width: {{ $layout['logo_width_mm'] }}mm;
            max-height: {{ $layout['logo_max_height_mm'] }}mm;
            height: auto;
            display: block;
        }

        .logo-missing {
            font-size: 4.5pt;
            color: #B91C1C;
            line-height: 1.05;
            text-align: left;
            word-break: break-all;
        }

        /* ~10–11px ≈ 8pt */
        .name {
            font-size: 8pt;
            font-weight: bold;
            line-height: 1.08;
            color: #111827;
            margin: 0 0 0.15mm 0;
            max-height: 17.5pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        /* ~8px ≈ 6pt */
        .category {
            font-size: 6pt;
            font-weight: normal;
            line-height: 1.08;
            color: #111827;
            margin: 0 0 0.1mm 0;
            max-height: 6.6pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        .room {
            font-size: 6pt;
            font-weight: bold;
            line-height: 1.08;
            color: #111827;
            margin: 0 0 0.25mm 0;
            max-height: 6.6pt;
            overflow: hidden;
            word-wrap: break-word;
        }

        .number-rule {
            border: 0;
            border-top: 0.4pt solid #D1D5DB;
            margin: 0 0 0.15mm 0;
            height: 0;
            padding: 0;
        }

        /* ~7px ≈ 5.5pt — MUST stay one line */
        .number {
            font-weight: bold;
            line-height: 1.05;
            letter-spacing: -0.04em;
            color: #111827;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
            word-break: keep-all;
            max-width: 100%;
        }

        .qr-wrap {
            display: inline-block;
            width: {{ $layout['qr_size_mm'] }}mm;
            height: {{ $layout['qr_size_mm'] }}mm;
            background: #fff;
            text-align: center;
            line-height: 0;
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
    // Pad only the last page so the cutting grid stays aligned.
    while (count($cells) < $perPage) {
        $cells[] = null;
    }
    $rows = array_chunk($cells, $columns);
@endphp
<div class="page"@if($isLastPage) style="page-break-after:auto;break-after:auto;"@endif>
    <table class="grid">
        @foreach($rows as $row)
        <tr>
            @foreach($row as $label)
            <td class="cell">
                @if($label)
                    @php
                        $invNo = (string) $label->inventory_number;
                        $invLen = mb_strlen($invNo);
                        // ~7px base; shrink slightly for long UNDIRA numbers (one line only).
                        $invPt = $invLen >= 30 ? 4.6 : ($invLen >= 26 ? 4.9 : ($invLen >= 22 ? 5.2 : 5.5));
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
