@php
    $isPdf = $isPdf ?? false;
    $autoPrint = $autoPrint ?? (! $isPdf);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] ?? 'Laporan Peminjaman' }}</title>
    @if($isPdf)
        <style>{!! file_get_contents(public_path('css/print.css')) !!}</style>
    @else
        <link rel="stylesheet" href="{{ asset('css/print.css') }}">
    @endif
</head>
<body class="{{ $isPdf ? '' : 'rpt-screen' }}">
@unless($isPdf)
<div class="no-print" style="margin-bottom:12px;">
    <button type="button" onclick="window.print()">Cetak</button>
    <a href="{{ route('reports.show', $type->value) }}">Kembali</a>
</div>
@endunless
<div class="rpt-doc">
    @include('reports._document-header', [
        'embedLogo' => $isPdf,
        'filters' => $filters,
        'meta' => $meta,
        'summary' => [
            'total_items' => count($rows),
            'campus_count' => 0,
            'building_count' => 0,
            'room_count' => 0,
            'aktif' => 0,
            'rusak' => 0,
            'dipinjam' => count($rows),
            'maintenance' => 0,
        ],
    ])
    <table class="rpt-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Nomor Approval</th>
                <th>No Peminjaman</th>
                <th>Peminjam</th>
                <th>Barang</th>
                <th>Tgl Pinjam</th>
                <th>Tgl Kembali</th>
                <th>Status</th>
                <th>Approved By</th>
                <th>Approved At</th>
                <th>Durasi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $b)
                @php
                    $item = $b->inventoryItem;
                    $end = $b->return_date ?? $b->expected_return_date;
                    $dur = ($b->borrow_date && $end) ? $b->borrow_date->diffInDays($end).' hari' : '—';
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $b->approval_number ?? '—' }}</td>
                    <td>PIN-{{ str_pad((string) $b->id, 6, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $b->borrower_name }}</td>
                    <td>{{ $item?->name }} ({{ $item?->inventory_number }})</td>
                    <td>{{ $b->plannedScheduleDate('borrow_date') }}@if($b->plannedScheduleTime('borrow_date')) {{ $b->plannedScheduleTime('borrow_date') }}@endif</td>
                    <td>{{ $b->return_date?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $b->status?->label() }}</td>
                    <td>{{ $b->approvedBy?->name ?? '—' }}</td>
                    <td>{{ $b->approved_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $dur }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="rpt-grand"><strong>Total Peminjaman:</strong> {{ number_format(count($rows)) }}</div>
</div>
@if($autoPrint)
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
@endif
</body>
</html>
