{{-- Official report header + filter + summary (print / PDF) --}}
@php
    $logoPath = public_path('images/logo-undira.png');
    $logoSrc = null;
    if (is_file($logoPath)) {
        $logoSrc = ($embedLogo ?? false)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : asset('images/logo-undira.png');
    }
    $orientation = $meta['orientation'] ?? 'portrait';
@endphp

<header class="rpt-header">
    <table class="rpt-header-table">
        <tr>
            <td style="width:56px;">
                @if($logoSrc)
                    <img class="rpt-logo" src="{{ $logoSrc }}" alt="Logo UNDIRA">
                @endif
            </td>
            <td>
                <p class="rpt-org">{{ $meta['organization'] ?? 'Universitas Dian Nusantara' }}</p>
                <h1 class="rpt-title">{{ $meta['report_title'] ?? 'Laporan Inventaris' }}</h1>
                <p class="rpt-subtitle">{{ $meta['title'] ?? 'Laporan Inventaris' }}</p>
                <div class="rpt-meta">
                    <p class="rpt-meta-row"><strong>Jenis laporan:</strong> {{ $meta['title'] ?? '-' }} ({{ $orientation === 'landscape' ? 'Landscape' : 'Portrait' }})</p>
                    <p class="rpt-meta-row"><strong>Tanggal cetak:</strong> {{ $meta['printed_date'] ?? ($meta['printed_at'] ?? '-') }}</p>
                    <p class="rpt-meta-row"><strong>Jam cetak:</strong> {{ $meta['printed_time'] ?? '-' }} WIB</p>
                    <p class="rpt-meta-row"><strong>Dicetak oleh:</strong> {{ $meta['user_name'] ?? '-' }}</p>
                </div>
            </td>
        </tr>
    </table>
</header>

@if(!empty($filters))
<section class="rpt-filters">
    <div class="rpt-filters-title">Filter yang digunakan</div>
    <table class="rpt-filters-grid">
        @foreach(collect($filters)->chunk(4) as $chunk)
            <tr>
                @foreach($chunk as $label => $value)
                    <td><span class="lbl">{{ $label }}:</span> <span class="val">{{ $value }}</span></td>
                @endforeach
                @for($i = $chunk->count(); $i < 4; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>
</section>
@endif

@php
    $summaryKind = $summary['kind'] ?? 'inventory';
@endphp

@if($summaryKind === 'room')
    @include('reports._room-borrowing-stats', ['summary' => $summary])
@elseif($summaryKind === 'vehicle')
    @include('reports._vehicle-borrowing-stats', ['summary' => $summary])
@else
    <table class="rpt-summary">
        <thead>
            <tr>
                <th>Total Inventaris</th>
                <th>Total Kampus</th>
                <th>Total Gedung</th>
                <th>Total Ruangan</th>
                <th>Total Aktif</th>
                <th>Total Rusak</th>
                <th>Total Dipinjam</th>
                <th>Total Maintenance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($summary['total_items'] ?? 0) }}</td>
                <td>{{ number_format($summary['campus_count'] ?? 0) }}</td>
                <td>{{ number_format($summary['building_count'] ?? 0) }}</td>
                <td>{{ number_format($summary['room_count'] ?? 0) }}</td>
                <td>{{ number_format($summary['aktif'] ?? 0) }}</td>
                <td>{{ number_format($summary['rusak'] ?? 0) }}</td>
                <td>{{ number_format($summary['dipinjam'] ?? 0) }}</td>
                <td>{{ number_format($summary['maintenance'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    @include('reports._composition')

    @include('reports._sub-category-recap', [
        'recap' => $summary['sub_category_recap'] ?? ['rows' => [], 'kind_count' => 0, 'total_units' => 0],
        'title' => 'REKAP SUB KATEGORI INVENTARIS',
        'variant' => 'global',
    ])
@endif
