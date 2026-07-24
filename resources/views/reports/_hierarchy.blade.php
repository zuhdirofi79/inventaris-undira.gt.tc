{{-- Professional grouped inventory tables (print / PDF / preview) --}}

@forelse($tree as $campusNode)
    <section class="rpt-campus">
        <h2 class="rpt-h-campus">{{ $campusNode['label'] }}</h2>

        @foreach($campusNode['buildings'] as $buildingNode)
            <h3 class="rpt-h-building">{{ $buildingNode['label'] }}</h3>

            @foreach($buildingNode['floors'] as $floorNode)
                <h4 class="rpt-h-floor">{{ $floorNode['label'] }}</h4>

                @foreach($floorNode['rooms'] as $roomNode)
                    <h5 class="rpt-h-room">{{ $roomNode['label'] }}</h5>

                    @foreach($roomNode['categories'] as $categoryNode)
                        <div class="rpt-h-category">
                            <span class="tag">Kategori</span> {{ $categoryNode['label'] }}
                        </div>

                        @foreach($categoryNode['sub_categories'] as $subNode)
                            <div class="rpt-h-sub">
                                <span class="tag">Subkategori</span> {{ $subNode['label'] }}
                            </div>

                            <div class="rpt-table-wrap">
                                <table class="rpt-table">
                                    <thead>
                                        <tr>
                                            <th class="rpt-col-no">No</th>
                                            <th class="rpt-col-inv">Nomor Inventaris</th>
                                            <th class="rpt-col-name">Nama Barang</th>
                                            <th class="rpt-col-cat">Kategori</th>
                                            <th class="rpt-col-sub">Subkategori</th>
                                            <th class="rpt-col-brand">Merk</th>
                                            <th class="rpt-col-sn">Serial Number</th>
                                            <th class="rpt-col-cond">Kondisi</th>
                                            <th class="rpt-col-status">Status</th>
                                            <th class="rpt-col-pic">PIC</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($subNode['items'] as $item)
                                            <tr>
                                                <td class="rpt-col-no">{{ str_pad((string) $loop->iteration, 3, '0', STR_PAD_LEFT) }}</td>
                                                <td class="rpt-col-inv">{{ $item->inventory_number }}</td>
                                                <td class="rpt-col-name">{{ $item->name }}</td>
                                                <td class="rpt-col-cat">{{ $categoryNode['label'] }}</td>
                                                <td class="rpt-col-sub">{{ $subNode['label'] }}</td>
                                                <td class="rpt-col-brand">{{ $item->brand ?: '—' }}</td>
                                                <td class="rpt-col-sn">{{ $item->serial_number ?: '—' }}</td>
                                                <td class="rpt-col-cond">{{ $item->condition?->label() ?? '—' }}</td>
                                                <td class="rpt-col-status">{{ $item->status?->label() ?? '—' }}</td>
                                                <td class="rpt-col-pic">{{ $item->picDisplayName() ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    @endforeach

                    <div class="rpt-total rpt-total-room">
                        <strong>Total Ruangan:</strong>
                        Inventaris {{ number_format($roomNode['totals']['count'] ?? 0) }}
                        <span class="sep">|</span>
                        Aktif {{ number_format($roomNode['totals']['aktif'] ?? 0) }}
                        <span class="sep">|</span>
                        Rusak {{ number_format($roomNode['totals']['rusak'] ?? 0) }}
                    </div>
                @endforeach
            @endforeach

            <div class="rpt-total rpt-total-building">
                <strong>Total Gedung {{ $buildingNode['label'] }}:</strong>
                Inventaris {{ number_format($buildingNode['totals']['count'] ?? 0) }}
                <span class="sep">|</span>
                Ruangan {{ number_format($buildingNode['room_count'] ?? 0) }}
            </div>
        @endforeach

        <div class="rpt-total rpt-total-campus">
            <strong>Total Kampus {{ $campusNode['label'] }}:</strong>
            Inventaris {{ number_format($campusNode['totals']['count'] ?? 0) }}
        </div>
    </section>
@empty
    <p>Tidak ada data inventaris sesuai filter.</p>
@endforelse
