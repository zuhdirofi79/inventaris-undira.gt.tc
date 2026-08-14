<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Label QR Inventaris</title>
    <style>
        /*
         * DomPDF mass labels — A4 portrait, 5×9 = 45 / page.
         *
         * CRITICAL (empty-page / sparse-page bugs):
         * - NEVER put page-break-inside:avoid on .page or full grid rows.
         *   DomPDF pushes overflowing "avoid" blocks → blank sheets.
         * - NEVER use border-spacing / height:100% / 100vh / min-height:297mm.
         * - DomPDF often ignores box-sizing: treat cell height as LABEL height;
         *   cell padding = cutting gap (content-box safe).
         * - Page break ONLY between chunk wrappers.
         * - Images MUST have fixed mm width+height (height:auto expands rows).
         * - Inventory number may wrap 1–2 lines; NEVER ellipsis / NEVER clip digits.
         */
        @page {
            size: A4 portrait;
            margin: {{ $layout['page_margin_mm'] }}mm;
        }

        * { margin: 0; padding: 0; }

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
            page-break-inside: auto;
        }

        .page-last {
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

        td.cell {
            width: {{ $layout['cell_width_pct'] }}%;
            height: {{ $layout['label_height_mm'] }}mm;
            padding: {{ $layout['cell_pad_y_mm'] }}mm {{ $layout['cell_pad_x_mm'] }}mm;
            vertical-align: top;
            overflow: hidden;
        }

        .label {
            width: 100%;
            height: {{ $layout['label_height_mm'] }}mm;
            border: 0.35pt solid #D1D5DB;
            background: #fff;
            overflow: hidden;
        }

        .label-empty {
            border: none;
            background: transparent;
        }

        table.label-inner {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
            margin: 0;
        }

        td.pad {
            height: {{ $layout['label_padding_mm'] }}mm;
            font-size: 0;
            line-height: 0;
            padding: 0;
        }

        td.pad-bottom {
            height: {{ $layout['label_padding_bottom_mm'] }}mm;
            font-size: 0;
            line-height: 0;
            padding: 0;
        }

        tr.main-row td {
            height: {{ $layout['qr_size_mm'] }}mm;
            max-height: {{ $layout['qr_size_mm'] }}mm;
            overflow: hidden;
        }

        td.col-text {
            width: {{ max(48, 100 - $layout['qr_col_width_pct']) }}%;
            vertical-align: top;
            padding: 0 0.4mm 0 {{ $layout['label_padding_mm'] }}mm;
            overflow: hidden;
        }

        td.col-qr {
            width: {{ $layout['qr_col_width_pct'] }}%;
            vertical-align: top;
            text-align: right;
            padding: 0 {{ $layout['label_padding_mm'] }}mm 0 0;
        }

        td.col-number {
            height: {{ $layout['number_row_mm'] }}mm;
            max-height: {{ $layout['number_row_mm'] }}mm;
            vertical-align: middle;
            padding: 0.15mm {{ $layout['label_padding_mm'] }}mm 0 {{ $layout['label_padding_mm'] }}mm;
            border-top: 0.25pt solid #E5E7EB;
            overflow: visible;
        }

        .logo-wrap {
            height: {{ $layout['logo_max_height_mm'] }}mm;
            max-height: {{ $layout['logo_max_height_mm'] }}mm;
            margin: 0 0 0.25mm 0;
            overflow: hidden;
            text-align: left;
            line-height: 0;
        }

        /* Fixed mm size — DomPDF height:auto on PNG often blows up row height. */
        img.logo {
            width: {{ $layout['logo_width_mm'] }}mm;
            height: {{ $layout['logo_max_height_mm'] }}mm;
            max-width: {{ $layout['logo_width_mm'] }}mm;
            max-height: {{ $layout['logo_max_height_mm'] }}mm;
            display: block;
        }

        .logo-missing {
            font-size: 4pt;
            color: #B91C1C;
            line-height: 1.05;
            text-align: left;
            word-break: break-all;
        }

        .name {
            font-weight: bold;
            line-height: 1.08;
            color: #111827;
            margin: 0 0 0.15mm 0;
            overflow: hidden;
            word-wrap: break-word;
        }

        .category {
            font-weight: normal;
            line-height: 1.05;
            color: #374151;
            margin: 0 0 0.1mm 0;
            max-height: 6.5pt;
            overflow: hidden;
            white-space: nowrap;
        }

        .room {
            font-weight: normal;
            line-height: 1.05;
            color: #374151;
            margin: 0;
            max-height: 6.5pt;
            overflow: hidden;
            white-space: nowrap;
        }

        /*
         * Inventory number: full-width bottom zone.
         * MUST show complete code — wrap 1–2 lines if needed.
         * NEVER text-overflow:ellipsis / NEVER overflow:hidden that clips digits.
         */
        .number {
            font-weight: bold;
            line-height: 1.12;
            color: #111827;
            text-align: left;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-all;
            overflow: visible;
        }

        .qr-wrap {
            width: {{ $layout['qr_size_mm'] }}mm;
            height: {{ $layout['qr_size_mm'] }}mm;
            background: #fff;
            text-align: center;
            line-height: 0;
            margin-left: auto;
            overflow: hidden;
        }

        img.qr {
            width: {{ $layout['qr_size_mm'] }}mm;
            height: {{ $layout['qr_size_mm'] }}mm;
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
    // Only pad the incomplete last row (table structure), never fill empty rows.
    $pageItems = $pageLabels->values()->all();
    $rows = array_chunk($pageItems, $columns);
    if ($rows !== []) {
        $lastIdx = count($rows) - 1;
        while (count($rows[$lastIdx]) < $columns) {
            $rows[$lastIdx][] = null;
        }
    }
@endphp
<div class="page{{ $isLastPage ? ' page-last' : '' }}">
    <table class="grid">
        @foreach($rows as $row)
        <tr>
            @foreach($row as $label)
            <td class="cell">
                @if($label)
                    <div class="label">
                        <table class="label-inner">
                            <tr>
                                <td class="pad" colspan="2"></td>
                            </tr>
                            <tr class="main-row">
                                <td class="col-text">
                                    <div class="logo-wrap">
                                        @if(!empty($logoSrc))
                                            <img class="logo" src="{{ $logoSrc }}" alt="Universitas Dian Nusantara">
                                        @else
                                            <div class="logo-missing">
                                                LOGO GAGAL<br>
                                                {{ \Illuminate\Support\Str::limit($logoMissingMessage ?: 'logo-undira.png tidak ditemukan', 80, '') }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="name" style="font-size: {{ $label->name_pt ?? 7 }}pt; max-height: {{ $label->name_max_height_pt ?? 8 }}pt;">{{ $label->name }}</div>
                                    <div class="category" style="font-size: {{ $label->category_pt ?? 5.8 }}pt;">{{ $label->category }}</div>
                                    <div class="room" style="font-size: {{ $label->room_pt ?? 5.8 }}pt;">{{ $label->room }}</div>
                                </td>
                                <td class="col-qr">
                                    <div class="qr-wrap">
                                        <img class="qr" src="{{ $label->qr_src }}" alt="QR">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="col-number" colspan="2">
                                    <div class="number" style="font-size: {{ $label->number_pt ?? 5.5 }}pt; letter-spacing: {{ $label->number_letter_spacing ?? '-0.02em' }};">{{ $label->inventory_number }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="pad-bottom" colspan="2"></td>
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
