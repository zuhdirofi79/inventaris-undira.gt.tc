<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Inventaris UNDIRA</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; }
        .summary { margin-bottom: 14px; }
        .summary span { display: inline-block; margin-right: 12px; }
    </style>
</head>
<body>
    <h1>Laporan Inventaris UNDIRA</h1>
    <div class="meta">Dicetak: {{ now()->format('d/m/Y H:i') }} | Total: {{ isset($summary) ? ($summary['total_items'] ?? $items->count()) : $items->count() }} item</div>

    @if(isset($summary))
    <div class="summary">
        <span>Nilai: Rp {{ number_format($summary['total_value'] ?? 0, 0, ',', '.') }}</span>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>No. Inventaris</th>
                <th>Nama</th>
                <th>Kategori</th>
                <th>Lokasi</th>
                <th>Status</th>
                <th>Kondisi</th>
                <th>Harga</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
            <tr>
                <td>{{ $item->inventory_number }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->category?->name ?? '-' }}</td>
                <td>{{ $item->full_location }}</td>
                <td>{{ $item->status?->label() ?? ($item->status?->value ?? '-') }}</td>
                <td>{{ $item->condition?->label() ?? ($item->condition?->value ?? '-') }}</td>
                <td>{{ $item->price ? number_format($item->price, 0, ',', '.') : '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="7">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
