@php
    $scheduleToday = $schedule_today ?? [];
    $scheduleUpcoming = $schedule_upcoming ?? [];
    $onlineUsers = $online_users ?? ['total' => 0, 'users' => []];
    $recordsUrl = $schedule_records_url ?? null;
@endphp

<style>
.dash-sched-card { border: 1px solid var(--ui-border, #E6EAF2); border-radius: 14px; background: #fff; height: 100%; }
.dash-sched-card .card-header {
    background: #fff;
    border-bottom: 1px solid var(--ui-border, #E6EAF2);
    font-weight: 700;
}
.dash-sched-item {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: .65rem .85rem;
    align-items: start;
    padding: .7rem 0;
    border-bottom: 1px solid #eef2f7;
}
.dash-sched-item:last-child { border-bottom: 0; }
.dash-sched-type {
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    border-radius: .45rem;
    padding: .2rem .45rem;
    background: #EEF0FA;
    color: #1F3C88;
    white-space: nowrap;
}
.dash-sched-type--ruangan { background: #E0F2F1; color: #00695C; }
.dash-sched-type--kendaraan { background: #FFF3E0; color: #E65100; }
.dash-sched-title { font-weight: 700; color: #0f172a; font-size: .92rem; line-height: 1.25; }
.dash-sched-sub, .dash-sched-meta { font-size: .78rem; color: #64748b; margin-top: .12rem; }
.dash-sched-time { font-size: .78rem; font-weight: 700; color: #334155; white-space: nowrap; }
.dash-sched-day { font-weight: 800; color: #1F3C88; font-size: .88rem; margin: .85rem 0 .35rem; }
.dash-sched-day:first-child { margin-top: 0; }
.dash-online-user {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .55rem 0;
    border-bottom: 1px solid #eef2f7;
}
.dash-online-user:last-child { border-bottom: 0; }
.dash-online-dot {
    width: .55rem;
    height: .55rem;
    border-radius: 50%;
    background: #2E7D32;
    display: inline-block;
    margin-right: .4rem;
    box-shadow: 0 0 0 3px rgba(46,125,50,.15);
}
.dash-online-name { font-weight: 700; color: #0f172a; font-size: .9rem; }
.dash-online-role { font-size: .75rem; color: #64748b; }
body.dark-mode .dash-sched-card { border-color: rgba(148,163,184,.25); background: transparent; }
body.dark-mode .dash-sched-card .card-header { background: transparent; border-color: rgba(148,163,184,.2); }
body.dark-mode .dash-sched-item,
body.dark-mode .dash-online-user { border-color: rgba(148,163,184,.15); }
body.dark-mode .dash-sched-title,
body.dark-mode .dash-online-name { color: #e2e8f0; }
body.dark-mode .dash-sched-sub,
body.dark-mode .dash-sched-meta,
body.dark-mode .dash-online-role { color: #94a3b8; }
body.dark-mode .dash-sched-time { color: #cbd5e1; }
body.dark-mode .dash-sched-day { color: #93c5fd; }
</style>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card dash-sched-card">
            <div class="card-header d-flex align-items-start justify-content-between gap-2">
                <div>
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
                @forelse($scheduleToday as $entry)
                    <div class="dash-sched-item">
                        <span class="dash-sched-type dash-sched-type--{{ $entry['type'] }}">{{ $entry['type_label'] }}</span>
                        <div class="min-w-0">
                            <div class="dash-sched-title text-break">{{ $entry['title'] }}</div>
                            <div class="dash-sched-sub text-break">{{ $entry['subtitle'] }}</div>
                            @if(!empty($entry['meta']))
                                <div class="dash-sched-meta text-break">{{ $entry['meta'] }}</div>
                            @endif
                        </div>
                        <div class="text-end">
                            <div class="dash-sched-time">{{ $entry['time_text'] }}</div>
                            <span class="badge text-bg-{{ $entry['status_tone'] }} mt-1">{{ $entry['status_label'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-body-secondary small py-2">
                        Tidak ada peminjaman yang disetujui untuk hari ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card dash-sched-card h-100">
            <div class="card-header d-flex align-items-start justify-content-between gap-2">
                <div>
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
                @forelse($scheduleUpcoming as $day)
                    <div class="dash-sched-day">{{ $day['date_label'] }}</div>
                    @foreach($day['entries'] as $entry)
                        <div class="dash-sched-item">
                            <span class="dash-sched-type dash-sched-type--{{ $entry['type'] }}">{{ $entry['type_label'] }}</span>
                            <div class="min-w-0">
                                <div class="dash-sched-title text-break">{{ $entry['title'] }}</div>
                                <div class="dash-sched-sub text-break">{{ $entry['subtitle'] }}</div>
                            </div>
                            <div class="text-end">
                                <div class="dash-sched-time">{{ $entry['time_text'] }}</div>
                            </div>
                        </div>
                    @endforeach
                @empty
                    <div class="text-body-secondary small py-2">
                        Tidak ada jadwal yang disetujui untuk tujuh hari ke depan.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card dash-sched-card h-100">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-circle text-success" style="font-size:.55rem"></i>
                    <span>Pengguna Online ({{ (int) ($onlineUsers['total'] ?? 0) }})</span>
                </div>
                <div class="small text-body-secondary fw-normal mt-1">
                    Pengguna yang sedang aktif menggunakan sistem LOADOUT.
                </div>
            </div>
            <div class="card-body">
                @forelse(($onlineUsers['users'] ?? []) as $online)
                    <div class="dash-online-user">
                        <div class="min-w-0">
                            <div class="dash-online-name text-break">
                                <span class="dash-online-dot" aria-hidden="true"></span>{{ $online['name'] }}
                            </div>
                            <div class="dash-online-role">{{ $online['role'] }}</div>
                        </div>
                        <span class="badge text-bg-success">Online</span>
                    </div>
                @empty
                    <div class="text-body-secondary small py-2">
                        Belum ada pengguna online saat ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
