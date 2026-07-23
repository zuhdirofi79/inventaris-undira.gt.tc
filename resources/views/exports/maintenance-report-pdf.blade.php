<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 14px; margin: 0 0 8px; }
        .meta { color: #666; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #eef5ee; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <div class="meta">
        Inventaris UNDIRA · Digenerate {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
        · {{ $report['meta']['count'] ?? 0 }} baris
        @if(isset($report['meta']['total_cost']))
            · Total biaya: Rp {{ number_format($report['meta']['total_cost'], 0, ',', '.') }}
        @endif
    </div>
    <table>
        <thead>
            <tr>
                @foreach($report['headings'] as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell === null || $cell === '' ? '-' : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['headings']) }}">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
