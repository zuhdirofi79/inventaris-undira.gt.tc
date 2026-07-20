<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Inventaris</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        .sheet { width: 100%; }
        .label {
            display: inline-block;
            width: 48%;
            vertical-align: top;
            border: 1px solid #ccc;
            padding: 8px;
            margin: 0 1% 12px 0;
            box-sizing: border-box;
            page-break-inside: avoid;
        }
        .number { font-size: 11px; font-weight: bold; margin-bottom: 4px; word-break: break-all; }
        .name { margin-bottom: 6px; }
        .meta { color: #555; font-size: 9px; }
        img.qr { width: 80px; height: 80px; }
    </style>
</head>
<body>
    <div class="sheet">
        @foreach($items as $item)
        <div class="label">
            <div class="number">{{ $item->inventory_number }}</div>
            <div class="name">{{ $item->name }}</div>
            <div class="meta">
                {{ $item->category?->name }}
                @if($item->full_location)<br>{{ $item->full_location }}@endif
            </div>
            @if($item->qr_code_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($item->qr_code_path))
                <img class="qr" src="{{ public_path('storage/'.$item->qr_code_path) }}" alt="QR">
            @endif
        </div>
        @endforeach
    </div>
</body>
</html>
