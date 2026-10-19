@php
    $submissions = $recent_borrowing_submissions ?? [];
    $approved = $recent_borrowing_approved ?? [];
    $submissionsUrl = $recent_borrowing_submissions_url ?? null;
    $approvedUrl = $recent_borrowing_approved_url ?? null;
@endphp

<style>
.dash-recent-card {
    border: 1px solid var(--ui-border, #E6EAF2);
    border-radius: 14px;
    overflow: hidden;
}
.dash-recent-card .card-header {
    background: #fff;
    border-bottom: 1px solid var(--ui-border, #E6EAF2);
    padding: .85rem 1rem !important;
    align-items: flex-start !important;
}
.dash-recent-card .card-header h5 {
    color: #1F3C88;
    font-weight: 800;
    font-size: .98rem;
    margin: 0;
}
.dash-recent-card .card-header .text-muted {
    font-weight: 500;
    font-size: .8rem;
    margin-top: .2rem;
}
.dash-recent-table {
    margin: 0;
    table-layout: fixed;
    width: 100%;
}
.dash-recent-table thead th {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .02em;
    color: #64748b;
    background: #F8FAFC;
    border-bottom: 1px solid #E6EAF2;
    white-space: nowrap;
    padding: .55rem .7rem;
    vertical-align: middle;
}
.dash-recent-table tbody td {
    padding: .55rem .7rem;
    vertical-align: top;
    font-size: .84rem;
    border-bottom: 1px solid #EEF2F7;
    word-break: break-word;
}
.dash-recent-table tbody tr:last-child td { border-bottom: 0; }
.dash-recent-table .col-jenis { width: 8.5rem; }
.dash-recent-table .col-nomor { width: 11rem; }
.dash-recent-table .col-peminjam { width: 9rem; }
.dash-recent-table .col-unit { width: 7.5rem; }
.dash-recent-table .col-kampus { width: 8.5rem; }
.dash-recent-table .col-jadwal { width: 9.5rem; }
.dash-recent-table .col-status { width: 7.5rem; }
.dash-recent-type {
    display: inline-block;
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    border-radius: .4rem;
    padding: .18rem .4rem;
    line-height: 1.3;
    background: #EEF0FA;
    color: #1F3C88;
}
.dash-recent-type--inventaris { background: #EEF0FA; color: #1F3C88; }
.dash-recent-type--ruangan { background: #E0F2F1; color: #00695C; }
.dash-recent-type--kendaraan { background: #FFF3E0; color: #E65100; }
.dash-recent-number a {
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
    word-break: break-all;
}
.dash-recent-number a:hover { text-decoration: underline; }
.dash-recent-meta { font-size: .74rem; color: #64748b; margin-top: .12rem; line-height: 1.35; }
.dash-recent-empty {
    padding: .85rem 1rem;
    color: #64748b;
    font-size: .84rem;
}
@media (max-width: 991.98px) {
    .dash-recent-table { table-layout: auto; }
    .dash-recent-table .col-jenis,
    .dash-recent-table .col-nomor,
    .dash-recent-table .col-peminjam,
    .dash-recent-table .col-unit,
    .dash-recent-table .col-kampus,
    .dash-recent-table .col-jadwal,
    .dash-recent-table .col-status { width: auto; }
}
body.dark-mode .dash-recent-card { border-color: rgba(148,163,184,.25); }
body.dark-mode .dash-recent-card .card-header { background: transparent; border-color: rgba(148,163,184,.2); }
body.dark-mode .dash-recent-card .card-header h5 { color: #93c5fd; }
body.dark-mode .dash-recent-table thead th { background: rgba(148,163,184,.08); color: #94a3b8; border-color: rgba(148,163,184,.2); }
body.dark-mode .dash-recent-table tbody td { border-color: rgba(148,163,184,.15); }
body.dark-mode .dash-recent-type { background: rgba(147,197,253,.15); color: #93c5fd; }
body.dark-mode .dash-recent-type--ruangan { background: rgba(45,212,191,.12); color: #5eead4; }
body.dark-mode .dash-recent-type--kendaraan { background: rgba(251,146,60,.12); color: #fdba74; }
body.dark-mode .dash-recent-meta,
body.dark-mode .dash-recent-empty { color: #94a3b8; }
body.dark-mode .dash-recent-number a { color: #93c5fd; }
</style>

@php
    $typeClass = static function (string $label): string {
        $normalized = mb_strtolower($label);
        if (str_contains($normalized, 'ruangan')) {
            return 'dash-recent-type--ruangan';
        }
        if (str_contains($normalized, 'kendaraan')) {
            return 'dash-recent-type--kendaraan';
        }

        return 'dash-recent-type--inventaris';
    };
@endphp

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card card-undira dash-recent-card">
            <div class="card-header d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <h5><i class="fas fa-inbox me-2"></i>Pengajuan Peminjaman Terbaru</h5>
                    <div class="text-muted">Pengajuan inventaris, ruangan, dan kendaraan yang baru dikirim oleh pengguna.</div>
                </div>
                @if($submissionsUrl)
                    <a href="{{ $submissionsUrl }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">Semua</a>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle dash-recent-table">
                        <thead>
                            <tr>
                                <th class="col-jenis">Jenis</th>
                                <th class="col-nomor">Pengajuan</th>
                                <th class="col-peminjam">Peminjam</th>
                                <th class="col-unit">Unit</th>
                                <th>Detail</th>
                                <th class="col-kampus">Lokasi Kampus</th>
                                <th class="col-jadwal">Jadwal</th>
                                <th class="col-status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions as $row)
                                <tr>
                                    <td><span class="dash-recent-type {{ $typeClass((string) $row['type_label']) }}">{{ $row['type_label'] }}</span></td>
                                    <td class="dash-recent-number">
                                        @if(!empty($row['number_url']))
                                            <a href="{{ $row['number_url'] }}">{{ $row['number'] }}</a>
                                        @else
                                            {{ $row['number'] }}
                                        @endif
                                    </td>
                                    <td>{{ $row['borrower'] }}</td>
                                    <td>{{ $row['unit'] ?? '—' }}</td>
                                    <td>
                                        <div>{{ $row['detail'] }}</div>
                                        @if(!empty($row['meta']))
                                            <div class="dash-recent-meta">{{ $row['meta'] }}</div>
                                        @endif
                                    </td>
                                    <td><small class="text-body-secondary">{{ $row['campus'] }}</small></td>
                                    <td><small class="text-body-secondary">{{ $row['schedule'] }}</small></td>
                                    <td><span class="badge text-bg-{{ $row['status_tone'] }}">{{ $row['status_label'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="dash-recent-empty">Belum ada pengajuan peminjaman terbaru.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card card-undira dash-recent-card">
            <div class="card-header d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <h5><i class="fas fa-clipboard-check me-2"></i>Peminjaman Disetujui Terbaru</h5>
                    <div class="text-muted">Daftar peminjaman yang telah disetujui Admin dan memiliki informasi jadwal yang tercatat.</div>
                </div>
                @if($approvedUrl)
                    <a href="{{ $approvedUrl }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">Semua</a>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle dash-recent-table">
                        <thead>
                            <tr>
                                <th class="col-jenis">Jenis</th>
                                <th class="col-nomor">Nomor Approval</th>
                                <th class="col-peminjam">Peminjam</th>
                                <th class="col-unit">Unit</th>
                                <th>Detail Peminjaman</th>
                                <th class="col-kampus">Lokasi Kampus</th>
                                <th class="col-jadwal">Jadwal Penggunaan</th>
                                <th class="col-status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($approved as $row)
                                <tr>
                                    <td><span class="dash-recent-type {{ $typeClass((string) $row['type_label']) }}">{{ $row['type_label'] }}</span></td>
                                    <td class="dash-recent-number">
                                        @if(!empty($row['number_url']))
                                            <a href="{{ $row['number_url'] }}">{{ $row['number'] }}</a>
                                        @else
                                            {{ $row['number'] }}
                                        @endif
                                    </td>
                                    <td>{{ $row['borrower'] }}</td>
                                    <td>{{ $row['unit'] ?? '—' }}</td>
                                    <td>
                                        <div>{{ $row['detail'] }}</div>
                                        @if(!empty($row['meta']))
                                            <div class="dash-recent-meta">{{ $row['meta'] }}</div>
                                        @endif
                                    </td>
                                    <td><small class="text-body-secondary">{{ $row['campus'] }}</small></td>
                                    <td><small class="text-body-secondary">{{ $row['schedule'] }}</small></td>
                                    <td><span class="badge text-bg-{{ $row['status_tone'] }}">{{ $row['status_label'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="dash-recent-empty">Belum ada peminjaman yang disetujui.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
