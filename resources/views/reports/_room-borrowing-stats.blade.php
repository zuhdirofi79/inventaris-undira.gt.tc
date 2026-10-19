@php
    $totals = $summary['totals'] ?? [];
    $topBorrowers = $summary['top_borrowers'] ?? [];
    $topRooms = $summary['top_rooms'] ?? [];
    $byActivity = $summary['by_activity'] ?? [];
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
            <th>Ruangan Digunakan</th>
            <th>Kampus Terlibat</th>
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
            <td>{{ number_format((int) ($totals['unique_rooms'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['unique_campuses'] ?? 0)) }}</td>
            <td>{{ isset($totals['approval_rate']) ? number_format((float) $totals['approval_rate'], 1).'%' : '—' }}</td>
        </tr>
    </tbody>
</table>

<table class="rpt-summary" style="margin-top:6px;">
    <thead>
        <tr>
            <th>Peserta Internal (Disetujui)</th>
            <th>Peserta Eksternal (Disetujui)</th>
            <th>Total Peserta (Disetujui)</th>
            <th>Total Jam Jadwal Disetujui</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ number_format((int) ($totals['participants_internal'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['participants_external'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($totals['participants_total'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($totals['scheduled_hours'] ?? 0), 1) }} jam</td>
        </tr>
    </tbody>
</table>

<section class="rpt-composition">
    <div class="rpt-composition-title">STATISTIK PEMINJAMAN RUANGAN</div>
    <p class="rpt-composition-note">Angka mengikuti filter laporan aktif. Ranking peminjam/ruangan dihitung dari transaksi unik yang disetujui.</p>

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
                            <th>Peserta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topBorrowers as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row['borrower'] }}</td>
                                <td>{{ $row['unit'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['approved_count']) }} kali</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['participants']) }} orang</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">B. Ruangan dengan Penggunaan Terbanyak</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Ruangan</th>
                            <th>Lokasi</th>
                            <th>Disetujui</th>
                            <th>Jam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topRooms as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row['room'] }}</td>
                                <td>{{ collect([$row['campus'], $row['building'], $row['floor']])->filter(fn ($v) => $v && $v !== '—')->implode(' · ') ?: '—' }}</td>
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
                <div class="rpt-composition-subtitle">C. Berdasarkan Jenis Kegiatan</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>Jenis Kegiatan</th>
                            <th>Pengajuan</th>
                            <th>Disetujui</th>
                            <th>Peserta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byActivity as $row)
                            <tr>
                                <td>{{ $row['activity'] }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['total']) }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['approved']) }}</td>
                                <td class="rpt-recap-qty">{{ number_format((int) $row['participants']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td class="rpt-composition-block">
                <div class="rpt-composition-subtitle">D. Ringkasan per Kampus</div>
                <table class="rpt-recap-table rpt-composition-table">
                    <thead>
                        <tr>
                            <th>Kampus</th>
                            <th>Total</th>
                            <th>Disetujui</th>
                            <th>Ditolak</th>
                            <th>Menunggu</th>
                            <th>Ruangan</th>
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
                                <td class="rpt-recap-qty">{{ number_format((int) $row['unique_rooms']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</section>
