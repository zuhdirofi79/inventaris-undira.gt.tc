<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Print Barcode 30 Label / A4</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 5mm;
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
            border-spacing: 2mm 1.2mm;
            table-layout: fixed;
        }

        td.cell {
            width: 33.33%;
            height: 27.5mm;
            vertical-align: top;
            padding: 0;
        }

        .label {
            width: 100%;
            height: 27.5mm;
            border: 0.5pt solid #c5cdd8;
            border-radius: 1.5mm;
            background: #fff;
            padding: 1mm 1.4mm;
            overflow: hidden;
        }

        .label-empty {
            border: 0.4pt dashed #e8edf3;
        }

        table.inner {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td.qr-col {
            width: 24mm;
            text-align: center;
            vertical-align: middle;
            padding: 0;
        }

        td.info-col {
            vertical-align: middle;
            padding: 0 0 0 1.2mm;
            text-align: left;
        }

        .logo {
            height: 3.2mm;
            width: auto;
            max-width: 16mm;
            display: block;
            margin: 0 0 0.6mm;
        }

        .name {
            font-size: 6.2pt;
            font-weight: bold;
            line-height: 1.1;
            text-transform: uppercase;
            max-height: 13pt;
            overflow: hidden;
            margin: 0 0 0.5mm;
        }

        .number {
            font-size: 4.8pt;
            color: #b91c1c;
            font-weight: bold;
            line-height: 1.15;
            word-break: break-all;
            max-height: 11pt;
            overflow: hidden;
            margin: 0 0 0.6mm;
        }

        img.qr {
            width: 22mm;
            height: 22mm;
            max-width: 22mm;
            max-height: 22mm;
            display: block;
            margin: 0 auto;
        }

        .meta {
            font-size: 4.5pt;
            color: #5f6b7a;
            line-height: 1.2;
            overflow: hidden;
            max-height: 12pt;
        }

        .loc {
            font-weight: bold;
            color: #334155;
        }
    </style>
</head>
<body>
@php
    /** @var \Illuminate\Support\Collection|\App\Models\InventoryItem[] $items */
    $chunks = $items->values()->chunk(30);
    if ($chunks->isEmpty()) {
        $chunks = collect([collect()]);
    }
@endphp

@foreach($chunks as $pageItems)
@php
    $cells = $pageItems->values()->all();
    while (count($cells) < 30) {
        $cells[] = null;
    }
    $rows = array_chunk($cells, 3);
@endphp
<div class="page">
    <table class="grid">
        @foreach($rows as $row)
        <tr>
            @foreach($row as $item)
            <td class="cell">
                @if($item)
                    @php
                        $roomLabel = $item->room?->code ?: ($item->room?->name ?? '—');
                        $catLine = trim(($item->category?->name ?? '—').' / '.($item->subCategory?->name ?? '—'));
                        $qrSrc = null;
                        if ($item->qr_code_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($item->qr_code_path)) {
                            $raw = \Illuminate\Support\Facades\Storage::disk('public')->get($item->qr_code_path);
                            $ext = strtolower(pathinfo($item->qr_code_path, PATHINFO_EXTENSION));
                            $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
                            $qrSrc = 'data:'.$mime.';base64,'.base64_encode($raw);
                        }
                    @endphp
                    <div class="label">
                        <table class="inner">
                            <tr>
                                <td class="qr-col">
                                    @if($qrSrc)
                                        <img class="qr" src="{{ $qrSrc }}" alt="QR">
                                    @else
                                        <div class="meta">QR N/A</div>
                                    @endif
                                </td>
                                <td class="info-col">
                                    @if(!empty($logoSrc))
                                        <img class="logo" src="{{ $logoSrc }}" alt="UNDIRA">
                                    @endif
                                    <div class="name">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($item->name, 36, '')) }}</div>
                                    <div class="number">{{ $item->inventory_number }}</div>
                                    <div class="meta">
                                        <span class="loc">{{ \Illuminate\Support\Str::limit($roomLabel, 24, '') }}</span><br>
                                        {{ \Illuminate\Support\Str::limit($catLine, 32, '') }}
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
