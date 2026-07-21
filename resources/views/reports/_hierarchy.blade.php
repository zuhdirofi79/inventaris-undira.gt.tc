{{-- Hierarchical report body shared by browser / print / PDF --}}
@php
    $isPdf = $isPdf ?? false;
@endphp

@forelse($tree as $campusNode)
    <div class="report-campus {{ $isPdf ? '' : 'mb-4' }}">
        <h2 class="report-h-campus">{{ $campusNode['label'] }}</h2>

        @foreach($campusNode['buildings'] as $buildingNode)
            <div class="report-building">
                <h3 class="report-h-building">{{ $buildingNode['label'] }}</h3>

                @foreach($buildingNode['floors'] as $floorNode)
                    <div class="report-floor">
                        <h4 class="report-h-floor">{{ $floorNode['label'] }}</h4>

                        @foreach($floorNode['rooms'] as $roomNode)
                            <div class="report-room">
                                <h5 class="report-h-room">{{ $roomNode['label'] }}</h5>

                                @foreach($roomNode['categories'] as $categoryNode)
                                    <div class="report-category">
                                        <div class="report-h-category">{{ $categoryNode['label'] }}</div>

                                        @foreach($categoryNode['sub_categories'] as $subNode)
                                            <div class="report-sub">
                                                <div class="report-h-sub">{{ $subNode['label'] }}</div>
                                                <ul class="report-items">
                                                    @foreach($subNode['items'] as $item)
                                                        <li>
                                                            <strong>{{ $item->name }}</strong>
                                                            <span class="report-meta">
                                                                — {{ $item->inventory_number }}
                                                                @if($item->status) · {{ $item->status->label() }} @endif
                                                                @if($item->condition) · {{ $item->condition->label() }} @endif
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach

                                <div class="report-subtotal">
                                    Subtotal Ruangan: {{ number_format($roomNode['totals']['count']) }} item
                                    @if(!empty($roomNode['by_category']))
                                        ·
                                        @foreach($roomNode['by_category'] as $cat => $cnt)
                                            {{ $cat }}: {{ $cnt }}@if(!$loop->last), @endif
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <div class="report-subtotal report-subtotal-floor">
                            Subtotal Lantai {{ $floorNode['label'] }}:
                            {{ number_format($floorNode['totals']['count']) }} item
                        </div>
                    </div>
                @endforeach

                <div class="report-subtotal report-subtotal-building">
                    Ringkasan {{ $buildingNode['label'] }}:
                    {{ number_format($buildingNode['totals']['count']) }} inventaris
                    · {{ number_format($buildingNode['room_count'] ?? 0) }} ruangan
                </div>
            </div>
        @endforeach

        <div class="report-campus-summary">
            <strong>Ringkasan {{ $campusNode['label'] }}</strong><br>
            Total Inventaris: {{ number_format($campusNode['totals']['count']) }}<br>
            @foreach($campusNode['by_category'] as $cat => $cnt)
                {{ $cat }}: {{ number_format($cnt) }}<br>
            @endforeach
        </div>
    </div>
@empty
    <p class="text-muted">Tidak ada data inventaris sesuai filter.</p>
@endforelse
