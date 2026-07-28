{{-- Statistik Komposisi Inventaris (executive overview; filter-aware) --}}
@php
    $composition = $summary['composition'] ?? [];
    $cat = $composition['by_category'] ?? ['rows' => [], 'total_units' => 0];
    $sub = $composition['by_sub_category'] ?? ['rows' => [], 'total_units' => 0, 'kind_count' => 0];
    $top = $composition['top_sub_categories'] ?? [];
    $cond = $composition['by_condition'] ?? ['rows' => [], 'total_units' => 0];
    $stat = $composition['by_status'] ?? ['rows' => [], 'total_units' => 0];
    $loc = $composition['location'] ?? [];
@endphp

<section class="rpt-composition">
    <div class="rpt-composition-title">STATISTIK KOMPOSISI INVENTARIS</div>
    <p class="rpt-composition-note">Komposisi mengikuti filter laporan yang sedang aktif.</p>

    <table class="rpt-composition-layout">
        <tr>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">A. Komposisi Berdasarkan Kategori</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th class="rpt-recap-qty">Jumlah</th>
                            <th class="rpt-recap-qty">Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cat['rows'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format($row['count']) }} Unit</td>
                                <td class="rpt-recap-qty">{{ number_format($row['percent']) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>TOTAL</td>
                            <td class="rpt-recap-qty">{{ number_format($cat['total_units'] ?? 0) }} Unit</td>
                            <td class="rpt-recap-qty">{{ ($cat['total_units'] ?? 0) > 0 ? '100%' : '0%' }}</td>
                        </tr>
                    </tfoot>
                </table>

                @if(!empty($cat['rows']))
                    <div class="rpt-bars">
                        @foreach($cat['rows'] as $row)
                            <div class="rpt-bar-item">
                                <div class="rpt-bar-label">{{ $row['label'] }}</div>
                                <table class="rpt-bar-table">
                                    <tr>
                                        <td class="rpt-bar-track-cell">
                                            <div class="rpt-bar-track">
                                                <div class="rpt-bar-fill" style="width: {{ max(0, min(100, (int) $row['percent'])) }}%;">&nbsp;</div>
                                            </div>
                                        </td>
                                        <td class="rpt-bar-pct">{{ number_format($row['percent']) }}%</td>
                                    </tr>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endif
            </td>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">B. Komposisi Berdasarkan Sub Kategori</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>Sub Kategori</th>
                            <th class="rpt-recap-qty">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sub['rows'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format($row['count']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="rpt-composition-layout">
        <tr>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">C. Top 10 Sub Kategori Terbanyak</div>
                <ol class="rpt-top-list">
                    @forelse($top as $row)
                        <li>
                            <span class="rpt-top-name">{{ $row['label'] }}</span>
                            <span class="rpt-top-dots"></span>
                            <span class="rpt-top-count">{{ number_format($row['count']) }}</span>
                        </li>
                    @empty
                        <li>Tidak ada data.</li>
                    @endforelse
                </ol>
            </td>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">D. Ringkasan Kondisi Inventaris</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>Status Kondisi</th>
                            <th class="rpt-recap-qty">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cond['rows'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format($row['count']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="rpt-recap-qty">{{ number_format($cond['total_units'] ?? 0) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>
        </tr>
        <tr>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">E. Ringkasan Status Inventaris</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th class="rpt-recap-qty">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stat['rows'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format($row['count']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="rpt-recap-qty">{{ number_format($stat['total_units'] ?? 0) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">F. Info Lokasi</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>Uraian</th>
                            <th class="rpt-recap-qty">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Kampus</td><td class="rpt-recap-qty">{{ number_format($loc['kampus'] ?? 0) }}</td></tr>
                        <tr><td>Gedung</td><td class="rpt-recap-qty">{{ number_format($loc['gedung'] ?? 0) }}</td></tr>
                        <tr><td>Lantai</td><td class="rpt-recap-qty">{{ number_format($loc['lantai'] ?? 0) }}</td></tr>
                        <tr><td>Ruangan</td><td class="rpt-recap-qty">{{ number_format($loc['ruangan'] ?? 0) }}</td></tr>
                        <tr><td>Kategori</td><td class="rpt-recap-qty">{{ number_format($loc['kategori'] ?? 0) }}</td></tr>
                        <tr><td>Sub Kategori</td><td class="rpt-recap-qty">{{ number_format($loc['sub_kategori'] ?? 0) }}</td></tr>
                        <tr><td>Total Inventaris</td><td class="rpt-recap-qty">{{ number_format($loc['total_inventaris'] ?? 0) }}</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</section>
