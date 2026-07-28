{{-- Sub-category quantity recap (additive; does not alter inventory table design) --}}
@php
    $recap = $recap ?? ['rows' => [], 'kind_count' => 0, 'total_units' => 0];
    $rows = $recap['rows'] ?? [];
    $title = $title ?? 'REKAP SUB KATEGORI INVENTARIS';
    $variant = $variant ?? 'global'; // global | building | floor | room
@endphp
@if(count($rows) > 0)
<section class="rpt-recap rpt-recap--{{ $variant }}">
    <div class="rpt-recap-title">{{ $title }}</div>
    <table class="rpt-recap-table">
        <thead>
            <tr>
                <th>Sub Kategori</th>
                <th class="rpt-recap-qty">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="rpt-recap-qty">{{ number_format($row['count']) }} Unit</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if($variant === 'global')
                <tr>
                    <td>TOTAL SUB KATEGORI : {{ number_format($recap['kind_count'] ?? count($rows)) }} Jenis</td>
                    <td class="rpt-recap-qty">TOTAL INVENTARIS : {{ number_format($recap['total_units'] ?? 0) }} Unit</td>
                </tr>
            @else
                <tr>
                    <td colspan="2" class="rpt-recap-qty">
                        Total Inventaris {{ number_format($recap['total_units'] ?? 0) }} Unit
                    </td>
                </tr>
            @endif
        </tfoot>
    </table>
</section>
@endif
