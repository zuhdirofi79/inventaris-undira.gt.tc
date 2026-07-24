{{-- Flat asset register table with QR (lengkap / rusak / nonaktif / dihapus / dll) --}}
@php
    /** @var \Illuminate\Support\Collection|\App\Models\InventoryItem[] $items */
    $eam = $eam ?? app(\App\Services\Reports\EamReportService::class);
@endphp

<div class="rpt-table-wrap">
    <table class="rpt-table">
        <thead>
            <tr>
                <th class="rpt-col-no">No</th>
                <th style="width:5%;">QR</th>
                <th class="rpt-col-inv">Nomor Inventaris</th>
                <th class="rpt-col-name">Nama Barang</th>
                <th class="rpt-col-cat">Kategori</th>
                <th class="rpt-col-sub">Subkategori</th>
                <th class="rpt-col-brand">Merk</th>
                <th class="rpt-col-sn">Serial Number</th>
                <th style="width:14%;">Lokasi</th>
                <th class="rpt-col-pic">PIC</th>
                <th class="rpt-col-status">Status</th>
                <th class="rpt-col-cond">Kondisi</th>
                <th style="width:7%;">Tgl Pengadaan</th>
                <th style="width:8%;">Nilai</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                @php $row = $eam->mapInventoryRow($item, $loop->iteration); @endphp
                <tr>
                    <td class="rpt-col-no">{{ $row['no'] }}</td>
                    <td style="text-align:center;">
                        @if($row['qr'])
                            <img src="{{ $row['qr'] }}" alt="QR" width="28" height="28" style="width:28px;height:28px;">
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $row['inventory_number'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['category'] }}</td>
                    <td>{{ $row['sub_category'] }}</td>
                    <td>{{ $row['brand'] }}</td>
                    <td>{{ $row['serial_number'] }}</td>
                    <td>{{ $row['location'] }}</td>
                    <td>{{ $row['pic'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['condition'] }}</td>
                    <td>{{ $row['purchase_date'] }}</td>
                    <td>{{ $row['price_formatted'] }}</td>
                </tr>
            @empty
                <tr><td colspan="14">Tidak ada data inventaris sesuai filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
