@php
    $isPdf = $isPdf ?? false;
    $autoPrint = $autoPrint ?? (! $isPdf);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] ?? 'Laporan Peminjaman Kendaraan' }}</title>
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
        'summary' => $summary ?? [],
    ])
    <table class="rpt-table rpt-borrow">
        <thead>
            <tr>
                <th>No</th>
                <th>Nomor Approval</th>
                <th>Nomor Pengajuan</th>
                <th>Peminjam</th>
                <th>Unit</th>
                <th>Kampus</th>
                <th>Jenis Layanan</th>
                <th>Tujuan</th>
                <th>Kendaraan</th>
                <th>Driver</th>
                <th>Penumpang</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Status</th>
                <th>Approved By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $b)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $b->approval_number ?? '—' }}</td>
                    <td>PKD-{{ str_pad((string) $b->id, 6, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $b->borrower_name }}</td>
                    <td>{{ $b->unit ?: '—' }}</td>
                    <td>{{ $b->campus?->name ?? '—' }}</td>
                    <td>{{ $b->service_type?->label() ?? '—' }}</td>
                    <td>{{ $b->tripText() }}</td>
                    <td>{{ $b->vehicle?->label() ?? '—' }}</td>
                    <td>{{ $b->driver?->name ?? '—' }}</td>
                    <td>{{ max(0, (int) $b->passenger_count) }}</td>
                    <td>{{ $b->starts_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $b->ends_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $b->displayLabel() }}</td>
                    <td>{{ $b->approvedBy?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" class="text-center">Tidak ada data untuk filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@unless($isPdf)
@if($autoPrint)
<script>window.print();</script>
@endif
@endunless
</body>
</html>
