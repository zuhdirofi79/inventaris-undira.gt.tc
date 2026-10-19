@php
    $scheduleToday = $schedule_today ?? [];
    $scheduleUpcoming = $schedule_upcoming ?? [];
    $onlineUsers = $online_users ?? ['total' => 0, 'users' => []];
    $recordsUrl = $schedule_records_url ?? null;
@endphp

<style>
.dash-sched-card {
    border: 1px solid var(--ui-border, #E6EAF2);
    border-radius: 14px;
    background: #fff;
}
.dash-sched-card .card-header {
    background: #fff;
    border-bottom: 1px solid var(--ui-border, #E6EAF2);
    font-weight: 700;
    padding: .85rem 1rem !important;
    align-items: flex-start !important;
}
.dash-sched-card .card-body {
    padding: .55rem 1rem .75rem !important;
}
.dash-sched-list {
    display: flex;
    flex-direction: column;
}
.dash-sched-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: .45rem .75rem;
    align-items: start;
    padding: .55rem 0;
    border-bottom: 1px solid #eef2f7;
}
.dash-sched-item:last-child { border-bottom: 0; padding-bottom: 0; }
.dash-sched-item:first-child { padding-top: .15rem; }
.dash-sched-type {
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    border-radius: .4rem;
    padding: .18rem .4rem;
    background: #EEF0FA;
    color: #1F3C88;
    white-space: nowrap;
    line-height: 1.3;
    margin-top: .12rem;
}
.dash-sched-type--inventaris { background: #EEF0FA; color: #1F3C88; }
.dash-sched-type--ruangan { background: #E0F2F1; color: #00695C; }
.dash-sched-type--kendaraan { background: #FFF3E0; color: #E65100; }
.dash-sched-title {
    font-weight: 700;
    color: #0f172a;
    font-size: .9rem;
    line-height: 1.3;
}
.dash-sched-sub,
.dash-sched-meta {
    font-size: .76rem;
    color: #64748b;
    margin-top: .1rem;
    line-height: 1.35;
}
.dash-sched-side {
    text-align: right;
    min-width: 5.5rem;
}
.dash-sched-time {
    font-size: .78rem;
    font-weight: 700;
    color: #334155;
    white-space: nowrap;
    line-height: 1.3;
}
.dash-sched-side .badge {
    font-size: .68rem;
    font-weight: 600;
    margin-top: .25rem;
}
.dash-sched-day {
    font-weight: 800;
    color: #1F3C88;
    font-size: .84rem;
    margin: .7rem 0 .2rem;
    padding-top: .15rem;
}
.dash-sched-day:first-child { margin-top: 0; padding-top: 0; }
.dash-sched-empty {
    color: #64748b;
    font-size: .84rem;
    padding: .35rem 0 .15rem;
}
.dash-online-list { display: flex; flex-direction: column; }
.dash-online-user {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .65rem;
    padding: .45rem 0;
    border-bottom: 1px solid #eef2f7;
}
.dash-online-user:last-child { border-bottom: 0; padding-bottom: 0; }
.dash-online-user:first-child { padding-top: .1rem; }
.dash-online-dot {
    width: .5rem;
    height: .5rem;
    border-radius: 50%;
    background: #2E7D32;
    display: inline-block;
    margin-right: .4rem;
    vertical-align: middle;
    box-shadow: 0 0 0 3px rgba(46,125,50,.12);
}
.dash-online-name {
    font-weight: 700;
    color: #0f172a;
    font-size: .88rem;
    line-height: 1.3;
}
.dash-online-role { font-size: .74rem; color: #64748b; margin-top: .08rem; }
.dash-online-user .badge { font-size: .68rem; font-weight: 600; }

@media (max-width: 575.98px) {
    .dash-sched-item {
        grid-template-columns: auto minmax(0, 1fr);
        gap: .35rem .55rem;
    }
    .dash-sched-side {
        grid-column: 2;
        text-align: left;
        min-width: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem .5rem;
        margin-top: .05rem;
    }
    .dash-sched-side .badge { margin-top: 0; }
}

body.dark-mode .dash-sched-card { border-color: rgba(148,163,184,.25); background: transparent; }
body.dark-mode .dash-sched-card .card-header { background: transparent; border-color: rgba(148,163,184,.2); }
body.dark-mode .dash-sched-item,
body.dark-mode .dash-online-user { border-color: rgba(148,163,184,.15); }
body.dark-mode .dash-sched-title,
body.dark-mode .dash-online-name { color: #e2e8f0; }
body.dark-mode .dash-sched-sub,
body.dark-mode .dash-sched-meta,
body.dark-mode .dash-sched-empty,
body.dark-mode .dash-online-role { color: #94a3b8; }
body.dark-mode .dash-sched-time { color: #cbd5e1; }
body.dark-mode .dash-sched-day { color: #93c5fd; }
</style>

<div class="row g-3 mb-4 align-items-stretch">
    <div class="col-12">
        <div class="card dash-sched-card">
            <div class="card-header d-flex align-items-start justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-calendar-day text-undira-blue"></i>
                        <span>Peminjaman Hari Ini</span>
                    </div>
                    <div class="small text-body-secondary fw-normal mt-1">
                        Daftar inventaris, ruangan, dan kendaraan yang dijadwalkan untuk digunakan hari ini.
                    </div>
                </div>
                @if($recordsUrl)
                    <a href="{{ $recordsUrl }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">Lihat Semua</a>
                @endif
            </div>
            <div class="card-body">
                <div class="dash-sched-list">
                    @forelse($scheduleToday as $entry)
                        <div class="dash-sched-item">
                            <span class="dash-sched-type dash-sched-type--{{ $entry['type'] }}">{{ $entry['type_label'] }}</span>
                            <div class="min-w-0">
                                <div class="dash-sched-title text-break">{{ $entry['title'] }}</div>
                                @if(!empty($entry['subtitle']))
                                    <div class="dash-sched-sub text-break">{{ $entry['subtitle'] }}</div>
                                @endif
                                @if(!empty($entry['meta']))
                                    <div class="dash-sched-meta text-break">{{ $entry['meta'] }}</div>
                                @endif
                            </div>
                            <div class="dash-sched-side">
                                <div class="dash-sched-time">{{ $entry['time_text'] }}</div>
                                <span class="badge text-bg-{{ $entry['status_tone'] }}">{{ $entry['status_label'] }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="dash-sched-empty">Tidak ada peminjaman terjadwal untuk hari ini.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7 d-flex">
        <div class="card dash-sched-card w-100">
            <div class="card-header d-flex align-items-start justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-calendar-week text-undira-green"></i>
                        <span>Jadwal Peminjaman Mendatang</span>
                    </div>
                    <div class="small text-body-secondary fw-normal mt-1">
                        Jadwal inventaris, ruangan, dan kendaraan yang telah disetujui untuk hari-hari berikutnya.
                    </div>
                </div>
                @if($recordsUrl)
                    <a href="{{ $recordsUrl }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">Lihat Semua</a>
                @endif
            </div>
            <div class="card-body">
                <div class="dash-sched-list">
                    @forelse($scheduleUpcoming as $day)
                        <div class="dash-sched-day">{{ $day['date_label'] }}</div>
                        @foreach($day['entries'] as $entry)
                            <div class="dash-sched-item">
                                <span class="dash-sched-type dash-sched-type--{{ $entry['type'] }}">{{ $entry['type_label'] }}</span>
                                <div class="min-w-0">
                                    <div class="dash-sched-title text-break">{{ $entry['title'] }}</div>
                                    @if(!empty($entry['subtitle']))
                                        <div class="dash-sched-sub text-break">{{ $entry['subtitle'] }}</div>
                                    @endif
                                    @if(!empty($entry['meta']))
                                        <div class="dash-sched-meta text-break">{{ $entry['meta'] }}</div>
                                    @endif
                                </div>
                                <div class="dash-sched-side">
                                    <div class="dash-sched-time">{{ $entry['time_text'] }}</div>
                                    <span class="badge text-bg-{{ $entry['status_tone'] }}">{{ $entry['status_label'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    @empty
                        <div class="dash-sched-empty">Tidak ada jadwal peminjaman yang disetujui untuk periode mendatang.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5 d-flex">
        <div class="card dash-sched-card w-100">
            <div class="card-header">
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-circle text-success" style="font-size:.55rem" aria-hidden="true"></i>
                        <span>Pengguna Online ({{ (int) ($onlineUsers['total'] ?? 0) }})</span>
                    </div>
                    <div class="small text-body-secondary fw-normal mt-1">
                        Pengguna yang sedang aktif menggunakan sistem LOADOUT.
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="dash-online-list">
                    @forelse(($onlineUsers['users'] ?? []) as $online)
                        <div class="dash-online-user">
                            <div class="min-w-0">
                                <div class="dash-online-name text-break">
                                    <span class="dash-online-dot" aria-hidden="true"></span>{{ $online['name'] }}
                                </div>
                                <div class="dash-online-role text-break">{{ $online['role'] }}</div>
                            </div>
                            <span class="badge text-bg-success flex-shrink-0">Online</span>
                        </div>
                    @empty
                        <div class="dash-sched-empty">Belum ada pengguna online saat ini.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
