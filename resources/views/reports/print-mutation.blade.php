@php $isPdf = $isPdf ?? false; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] ?? 'Laporan Mutasi' }}</title>
    @if($isPdf)
        <style>{!! file_get_contents(public_path('css/print.css')) !!}</style>
    @else
        <link rel="stylesheet" href="{{ asset('css/print.css') }}">
    @endif
</head>
<body class="{{ $isPdf ? '' : 'rpt-screen' }}">
@unless($isPdf)
<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak</button> <a href="{{ route('reports.show', $type->value) }}">Kembali</a></div>
@endunless
<div class="rpt-doc">
    @include('reports._document-header', ['embedLogo' => $isPdf, 'filters' => $filters, 'meta' => $meta, 'summary' => ['total_items'=>count($rows),'campus_count'=>0,'building_count'=>0,'room_count'=>0,'aktif'=>0,'rusak'=>0,'dipinjam'=>0,'maintenance'=>0]])
    <table class="rpt-table">
        <thead>
            <tr>
                <th>No</th><th>Barang</th><th>Lokasi Lama</th><th>Lokasi Baru</th><th>Tanggal</th><th>PIC / Petugas</th><th>Alasan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $mut)
                @php
                    $item = $mut->inventoryItem;
                    $fmt = fn ($room) => $room ? trim(($room->code ? $room->code.' — ' : '').$room->name) : '—';
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item?->name }} ({{ $item?->inventory_number }})</td>
                    <td>{{ $fmt($mut->fromRoom) }}</td>
                    <td>{{ $fmt($mut->toRoom) }}</td>
                    <td>{{ $mut->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $mut->movedBy?->name ?? ($item?->picDisplayName() ?: '—') }}</td>
                    <td>{{ $mut->reason ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="rpt-grand"><strong>Total Mutasi:</strong> {{ number_format(count($rows)) }}</div>
</div>
@unless($isPdf)<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),300));</script>@endunless
</body>
</html>
