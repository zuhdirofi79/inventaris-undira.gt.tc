@php $isPdf = $isPdf ?? false; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] ?? 'Laporan Maintenance' }}</title>
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
    @include('reports._document-header', ['embedLogo' => $isPdf, 'filters' => $filters, 'meta' => $meta, 'summary' => ['total_items'=>count($rows),'campus_count'=>0,'building_count'=>0,'room_count'=>0,'aktif'=>0,'rusak'=>0,'dipinjam'=>0,'maintenance'=>count($rows)]])
    <table class="rpt-table">
        <thead>
            <tr>
                <th>No</th><th>No WO</th><th>No Inventaris</th><th>Barang</th><th>Lokasi</th>
                <th>Tgl Masuk</th><th>Mulai</th><th>Selesai</th><th>Teknisi</th><th>Status</th><th>Durasi</th><th>Biaya</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $m)
                @php
                    $item = $m->inventoryItem;
                    $dur = '—';
                    if ($m->started_at && $m->completed_at) $dur = $m->started_at->diffInDays($m->completed_at).' hari';
                    elseif ($m->service_date && $m->completed_at) $dur = $m->service_date->diffInDays($m->completed_at).' hari';
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $m->maintenance_number }}</td>
                    <td>{{ $item?->inventory_number }}</td>
                    <td>{{ $item?->name }}</td>
                    <td>{{ $item?->locationDisplayName() }}</td>
                    <td>{{ $m->service_date?->format('d/m/Y') }}</td>
                    <td>{{ $m->started_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $m->completed_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $m->technicianUser?->name ?? ($m->technician ?: '—') }}</td>
                    <td>{{ $m->status?->label() }}</td>
                    <td>{{ $dur }}</td>
                    <td>Rp {{ number_format((float)($m->cost ?? $m->estimated_cost ?? 0), 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="rpt-grand"><strong>Total WO:</strong> {{ number_format(count($rows)) }}</div>
</div>
@unless($isPdf)<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),300));</script>@endunless
</body>
</html>
