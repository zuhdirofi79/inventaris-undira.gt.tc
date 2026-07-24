{{-- Professional grouped inventory tables (print / PDF / preview) --}}
@php
    $orientation = $meta['orientation'] ?? 'portrait';
    $isLandscape = $orientation === 'landscape';
    $eam = $eam ?? app(\App\Services\Reports\EamReportService::class);
@endphp

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

                            @include('reports._flat-table', [
                                'items' => collect($subNode['items']),
                                'meta' => $meta,
                                'eam' => $eam,
                                'startNo' => 0,
                            ])
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
