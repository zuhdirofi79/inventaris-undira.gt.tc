@php
    $totals = $summary['totals'] ?? [];
    $topBorrowers = $summary['top_borrowers'] ?? [];
    $topVehicles = $summary['top_vehicles'] ?? [];
    $topDrivers = $summary['top_drivers'] ?? [];
    $byDestination = $summary['by_destination'] ?? [];
    $byCampus = $summary['by_campus'] ?? [];
@endphp

<table class="rpt-summary">
    <thead>
        <tr>
            <th>Total Pengajuan</th>
            <th>Disetujui</th>
            <th>Menunggu</th>
            <th>Ditolak</th>
            <th>Selesai (Jadwal)</th>
            <th>Kendaraan Ditugaskan</th>
            <th>Pengemudi Ditugaskan</th>
            <th>Tingkat Persetujuan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ number_format((int) ($totals['total'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['approved'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['pending'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['rejected'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['completed'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['unique_vehicles'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['unique_drivers'] ?? 0)) }}</td>
            <td>{{ isset($totals['approval_rate']) ? number_format((float) $totals['approval_rate'], 1).'%' : '—' }}</td>
        </tr>
    </tbody>
</table>

<table class="rpt-summary" style="margin-top:6px;">
    <thead>
        <tr>
            <th>Perjalanan dengan Kendaraan Operasional</th>
            <th>Perjalanan GoCar</th>
            <th>Total Penumpang (Disetujui)</th>
            <th>Total Jam Jadwal Disetujui</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ number_format((int) ($totals['assigned_vehicle_trips'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['gocar_trips'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['passengers_total'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($totals['scheduled_hours'] ?? 0), 1) }} jam</td>
        </tr>
    </tbody>
</table>

<section class="rpt-composition">
    <div class="rpt-composition-title">STATISTIK PEMINJAMAN KENDARAAN</div>
    <p class="rpt-composition-note">Angka mengikuti filter laporan aktif. Ranking dihitung dari transaksi unik yang disetujui.</p>

    <table class="rpt-composition-layout">
        <tr>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">A. Top Peminjam &amp; Unit (Disetujui)</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Peminjam</th>
                            <th>Unit</th>
                            <th>Disetujui</th>
                            <th>Penumpang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topBorrowers as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row['borrower'] }}</td>
                                <td>{{ $row['unit'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['approved_count']) }} kali</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['passengers']) }} orang</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">B. Kendaraan yang Paling Sering Ditugaskan</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kendaraan</th>
                            <th>Plat</th>
                            <th>Penugasan</th>
                            <th>Jam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topVehicles as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row['vehicle'] }}{{ $row['category'] !== '—' ? ' ('.$row['category'].')' : '' }}</td>
                                <td>{{ $row['plate'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['approved_count']) }}</td>
                                <td class="rpt-recap-qty">{{ number_format((float) $row['scheduled_hours'], 1) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="rpt-composition-layout">
        <tr>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">C. Penugasan Pengemudi</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Pengemudi</th>
                            <th>Penugasan</th>
                            <th>Selesai</th>
                            <th>Penumpang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topDrivers as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row['driver'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['approved_count']) }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['completed']) }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['passengers']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">D. Tujuan / Rute Terbanyak</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>Rute</th>
                            <th>Tujuan</th>
                            <th>Disetujui</th>
                            <th>Penumpang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byDestination as $row)
                            <tr>
                                <td>{{ $row['route'] }}</td>
                                <td>{{ $row['destination'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['approved_count']) }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['passengers']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="rpt-composition-subtitle" style="margin-top:8px;">E. Ringkasan per Kampus (Lokasi Peminjaman)</div>
    <table class="rpt-recap-table rpt-composition-table">
        <thead>
            <tr>
                <th>Kampus</th>
                <th>Total</th>
                <th>Disetujui</th>
                <th>Ditolak</th>
                <th>Menunggu</th>
                <th>Kendaraan Ditugaskan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byCampus as $row)
                <tr>
                    <td>{{ $row['campus'] }}</td>
                    <td class="rpt-recap-qty">{{ number_format((int) $row['total']) }}</td>
                    <td class="rpt-recap-qty">{{ number_format((int) $row['approved']) }}</td>
                    <td class="rpt-recap-qty">{{ number_format((int) $row['rejected']) }}</td>
                    <td class="rpt-recap-qty">{{ number_format((int) $row['pending']) }}</td>
                    <td class="rpt-recap-qty">{{ number_format((int) $row['assigned_vehicles']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
