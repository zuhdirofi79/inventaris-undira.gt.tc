@php
    $submissions = $recent_borrowing_submissions ?? [];
    $approved = $recent_borrowing_approved ?? [];
    $submissionsUrl = $recent_borrowing_submissions_url ?? null;
    $approvedUrl = $recent_borrowing_approved_url ?? null;
@endphp

<style>
.dash-recent-card .card-header {
    background: #fff;
    border-bottom: 1px solid var(--ui-border, #E6EAF2);
}
.dash-recent-card .card-header h5 {
    color: #1F3C88;
    font-weight: 800;
    font-size: 1rem;
}
.dash-recent-card .card-header .text-muted {
    font-weight: 500;
    font-size: 0.8rem;
}
.dash-recent-type {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: #64748b;
}
.dash-recent-number a {
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
}
.dash-recent-number a:hover { text-decoration: underline; }
.dash-recent-meta { font-size: 0.75rem; color: #64748b; }
body.dark-mode .dash-recent-card .card-header { background: transparent; border-color: rgba(148,163,184,.2); }
body.dark-mode .dash-recent-card .card-header h5 { color: #93c5fd; }
body.dark-mode .dash-recent-type,
body.dark-mode .dash-recent-meta { color: #94a3b8; }
body.dark-mode .dash-recent-number a { color: #93c5fd; }
</style>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card card-undira dash-recent-card">
            <div class="card-header d-flex justify-content-between align-items-start gap-2">
                <div>
                    <h5 class="mb-1"><i class="fas fa-inbox me-2"></i>Pengajuan Peminjaman Terbaru</h5>
                    <div class="text-muted">Pengajuan inventaris, ruangan, dan kendaraan yang baru dikirim oleh pengguna.</div>
                </div>
                @if($submissionsUrl)
                    <a href="{{ $submissionsUrl }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">Semua</a>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>Pengajuan</th>
                                <th>Peminjam</th>
                                <th>Detail</th>
                                <th>Lokasi Kampus</th>
                                <th>Jadwal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions as $row)
                                <tr>
                                    <td><span class="dash-recent-type">{{ $row['type_label'] }}</span></td>
                                    <td class="dash-recent-number">
                                        @if(!empty($row['number_url']))
                                            <a href="{{ $row['number_url'] }}">{{ $row['number'] }}</a>
                                        @else
                                            {{ $row['number'] }}
                                        @endif
                                    </td>
                                    <td>{{ $row['borrower'] }}</td>
                                    <td>
                                        <div>{{ $row['detail'] }}</div>
                                        @if(!empty($row['meta']))
                                            <div class="dash-recent-meta">{{ $row['meta'] }}</div>
                                        @endif
                                    </td>
                                    <td><small>{{ $row['campus'] }}</small></td>
                                    <td><small>{{ $row['schedule'] }}</small></td>
                                    <td><span class="badge text-bg-{{ $row['status_tone'] }}">{{ $row['status_label'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-0">
                                        <x-empty-state
                                            icon="fa-inbox"
                                            title="Belum ada pengajuan peminjaman terbaru"
                                            message="Pengajuan inventaris, ruangan, dan kendaraan yang baru dikirim akan muncul di sini."
                                        />
                                    </td>
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
                <div>
                    <h5 class="mb-1"><i class="fas fa-clipboard-check me-2"></i>Peminjaman Disetujui Terbaru</h5>
                    <div class="text-muted">Daftar peminjaman yang telah disetujui Admin dan memiliki informasi jadwal yang tercatat.</div>
                </div>
                @if($approvedUrl)
                    <a href="{{ $approvedUrl }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">Semua</a>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>Nomor Approval</th>
                                <th>Peminjam</th>
                                <th>Detail Peminjaman</th>
                                <th>Lokasi Kampus</th>
                                <th>Jadwal Penggunaan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($approved as $row)
                                <tr>
                                    <td><span class="dash-recent-type">{{ $row['type_label'] }}</span></td>
                                    <td class="dash-recent-number">
                                        @if(!empty($row['number_url']))
                                            <a href="{{ $row['number_url'] }}">{{ $row['number'] }}</a>
                                        @else
                                            {{ $row['number'] }}
                                        @endif
                                    </td>
                                    <td>{{ $row['borrower'] }}</td>
                                    <td>
                                        <div>{{ $row['detail'] }}</div>
                                        @if(!empty($row['meta']))
                                            <div class="dash-recent-meta">{{ $row['meta'] }}</div>
                                        @endif
                                    </td>
                                    <td><small>{{ $row['campus'] }}</small></td>
                                    <td><small>{{ $row['schedule'] }}</small></td>
                                    <td><span class="badge text-bg-{{ $row['status_tone'] }}">{{ $row['status_label'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-0">
                                        <x-empty-state
                                            icon="fa-clipboard-check"
                                            title="Belum ada peminjaman yang disetujui"
                                            message="Peminjaman yang telah disetujui Admin akan muncul di sini."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
