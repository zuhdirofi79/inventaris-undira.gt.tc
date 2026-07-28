{{-- Inventory asset table — portrait (compact) vs landscape (full) --}}
@php
    /** @var \Illuminate\Support\Collection|\App\Models\InventoryItem[] $items */
    $eam = $eam ?? app(\App\Services\Reports\EamReportService::class);
    $orientation = $meta['orientation'] ?? 'portrait';
    $isLandscape = $orientation === 'landscape';
    $startNo = (int) ($startNo ?? 0);
    $colspan = $isLandscape ? 14 : 8;
@endphp

<div class="rpt-table-wrap">
    <table class="rpt-table rpt-table--{{ $isLandscape ? 'landscape' : 'portrait' }}">
        <thead>
            <tr>
                <th class="rpt-col-no">No</th>
                <th class="rpt-col-qr">QR</th>
                <th class="rpt-col-inv">Nomor Inventaris</th>
                <th class="rpt-col-name">Nama Barang</th>
                @if($isLandscape)
                    <th class="rpt-col-cat">Kategori</th>
                    <th class="rpt-col-sub">Subkategori</th>
                    <th class="rpt-col-brand">Merk</th>
                    <th class="rpt-col-sn">Serial Number</th>
                @endif
                <th class="rpt-col-loc">Lokasi</th>
                @if($isLandscape)
                    <th class="rpt-col-pic">PIC</th>
                @endif
                <th class="rpt-col-status">Status</th>
                <th class="rpt-col-cond">Kondisi</th>
                <th class="rpt-col-year">Tahun Pengadaan</th>
                @if($isLandscape)
                    <th class="rpt-col-date">Tgl Pengadaan</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                @php $row = $eam->mapInventoryRow($item, $startNo + $loop->iteration); @endphp
                <tr>
                    <td class="rpt-col-no rpt-cell">{{ $row['no'] }}</td>
                    <td class="rpt-col-qr rpt-qr-cell">
                        @if($row['qr'])
                            <img class="rpt-qr" src="{{ $row['qr'] }}" alt="QR">
                        @else
                            —
                        @endif
                    </td>
                    <td class="rpt-col-inv rpt-cell">{{ $row['inventory_number'] }}</td>
                    <td class="rpt-col-name rpt-cell">{{ $row['name'] }}</td>
                    @if($isLandscape)
                        <td class="rpt-col-cat rpt-cell">{{ $row['category'] }}</td>
                        <td class="rpt-col-sub rpt-cell">{{ $row['sub_category'] }}</td>
                        <td class="rpt-col-brand rpt-cell">{{ $row['brand'] }}</td>
                        <td class="rpt-col-sn rpt-cell">{{ $row['serial_number'] }}</td>
                    @endif
                    <td class="rpt-col-loc rpt-cell">{{ $row['location'] }}</td>
                    @if($isLandscape)
                        <td class="rpt-col-pic rpt-cell">{{ $row['pic'] }}</td>
                    @endif
                    <td class="rpt-col-status rpt-cell">{{ $row['status'] }}</td>
                    <td class="rpt-col-cond rpt-cell">{{ $row['condition'] }}</td>
                    <td class="rpt-col-year rpt-cell">{{ $row['purchase_year'] }}</td>
                    @if($isLandscape)
                        <td class="rpt-col-date rpt-cell">{{ $row['purchase_date'] }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="rpt-cell">Tidak ada data inventaris sesuai filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
