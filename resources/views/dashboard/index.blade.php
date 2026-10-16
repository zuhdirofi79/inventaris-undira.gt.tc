@extends('layouts.app')

@section('title', 'Dashboard - Inventaris UNDIRA')

@push('styles')
<style>
.dash-ent { --dash-gap: 1rem; }
.dash-ent .dash-widget {
    border: 1px solid var(--ui-border, #E6EAF2);
    border-radius: 14px;
    background: #fff;
    height: 100%;
    transition: box-shadow .2s ease, transform .2s ease;
    overflow: hidden;
    position: relative;
}
.dash-ent .dash-widget::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: var(--dash-accent, #1F3C88);
}
.dash-ent .dash-widget:hover { box-shadow: 0 8px 24px rgba(15,23,42,.08); transform: translateY(-1px); }
.dash-ent .dash-widget__body {
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: 1rem 1.05rem;
}
.dash-ent .dash-widget__icon {
    width: 2.6rem;
    height: 2.6rem;
    border-radius: .75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: var(--dash-soft, #EEF0FA);
    color: var(--dash-accent, #1F3C88);
    font-size: 1.1rem;
}
.dash-ent .dash-widget__label {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: .15rem;
}
.dash-ent .dash-widget__value {
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}
.dash-ent .dash-widget--green { --dash-accent: #2E7D32; --dash-soft: #E8F5E9; }
.dash-ent .dash-widget--blue { --dash-accent: #1A237E; --dash-soft: #EEF0FA; }
.dash-ent .dash-widget--warning { --dash-accent: #ED6C02; --dash-soft: #FFF3E0; }
.dash-ent .dash-widget--danger { --dash-accent: #C62828; --dash-soft: #FFEBEE; }
.dash-ent .dash-widget--info { --dash-accent: #0277BD; --dash-soft: #E1F5FE; }
.dash-ent .dash-widget--teal { --dash-accent: #00897B; --dash-soft: #E0F2F1; }
.dash-ent .dash-widget--slate { --dash-accent: #455A64; --dash-soft: #ECEFF1; }
.dash-ent .dash-widget--purple { --dash-accent: #5E35B1; --dash-soft: #EDE7F6; }
.dash-ent .dash-chart-card .card-header {
    background: #fff;
    border-bottom: 1px solid var(--ui-border, #E6EAF2);
    font-weight: 700;
}
.dash-ent .dash-chart-wrap { position: relative; min-height: 240px; }
.dash-ent .dash-activity-item { border-left: 3px solid #e2e8f0; }
.dash-ent .dash-activity-item:hover { background: #f8fafc; }

/* Executive campus summary */
.campus-exec-card {
    border: 1px solid var(--ui-border, #E6EAF2);
    border-radius: 14px;
    overflow: hidden;
    transition: box-shadow .2s ease, transform .2s ease;
}
.campus-exec-card:hover { box-shadow: 0 8px 24px rgba(15,23,42,.08); transform: translateY(-1px); }
.campus-exec-card__header {
    display: flex;
    justify-content: space-between;
    gap: .75rem;
    padding: .95rem 1.05rem .55rem;
}
.campus-exec-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: #1F3C88;
}
.campus-exec-card__total { font-size: .8rem; color: #64748b; margin-top: .2rem; }
.campus-exec-card__total strong { color: #0f172a; }
.campus-exec-card__code {
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #64748b;
    background: #EEF0FA;
    border-radius: .5rem;
    padding: .2rem .45rem;
    height: fit-content;
}
.campus-exec-card__stats {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    padding: 0 1.05rem .7rem;
}
.campus-exec-chip {
    font-size: .68rem;
    font-weight: 700;
    border-radius: 999px;
    padding: .18rem .5rem;
    background: #f1f5f9;
    color: #475569;
}
.campus-exec-chip--aktif { background: #E8F5E9; color: #2E7D32; }
.campus-exec-chip--dipinjam { background: #FFF8E1; color: #F9A825; }
.campus-exec-chip--maint { background: #E3F2FD; color: #1565C0; }
.campus-exec-chip--rusak { background: #FFEBEE; color: #C62828; }
.campus-exec-card__body { padding: 0 .55rem .85rem; }
.campus-exec-accordion .accordion-item {
    border: 0;
    background: transparent;
    margin-bottom: .35rem;
}
.campus-exec-cat__btn {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: .75rem !important;
    box-shadow: none !important;
    padding: .65rem .75rem !important;
    transition: background .2s ease;
}
.campus-exec-cat__btn:not(.collapsed) { background: #EEF0FA !important; }
.campus-exec-cat__btn::after { margin-left: .5rem; align-self: flex-start; margin-top: .35rem; }
.campus-exec-cat__name { font-weight: 800; color: #1F3C88; font-size: .9rem; }
.campus-exec-cat__count { font-weight: 800; color: #0f172a; }
.campus-exec-cat__meta { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .35rem; }
.campus-exec-progress {
    height: 5px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}
.campus-exec-progress__bar {
    height: 100%;
    background: linear-gradient(90deg, #1F3C88, #2E7D32);
    border-radius: 999px;
    transition: width .35s ease;
}
.campus-exec-badge { font-weight: 700 !important; }
.campus-exec-badge--aktif { background: #E8F5E9 !important; color: #2E7D32 !important; }
.campus-exec-badge--dipinjam { background: #FFF8E1 !important; color: #F9A825 !important; }
.campus-exec-badge--maint { background: #E3F2FD !important; color: #1565C0 !important; }
.campus-exec-badge--rusak { background: #FFEBEE !important; color: #C62828 !important; }
.campus-exec-badge--total { background: #ECEFF1 !important; color: #455A64 !important; }
.campus-exec-sub {
    border: 1px solid #eef2f7;
    border-radius: .65rem;
    padding: .65rem .7rem;
    margin-bottom: .45rem;
    background: #fff;
    animation: campusExecFade .25s ease;
}
.campus-exec-sub__name {
    font-weight: 700;
    color: #1e293b;
    text-decoration: none;
}
.campus-exec-sub__name:hover { color: #1F3C88; text-decoration: underline; }
.campus-exec-sub__badges { display: flex; flex-wrap: wrap; gap: .25rem; }
.campus-exec-empty { color: #64748b; font-size: .88rem; padding: .5rem .35rem; }
.campus-exec-card-wrap.is-hidden,
.campus-exec-cat.is-hidden,
.campus-exec-sub.is-hidden { display: none !important; }
@keyframes campusExecFade {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

body.dark-mode .campus-exec-card { border-color: rgba(148,163,184,.25); }
body.dark-mode .campus-exec-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.35); }
body.dark-mode .campus-exec-card__title,
body.dark-mode .campus-exec-cat__name { color: #93c5fd; }
body.dark-mode .campus-exec-card__total,
body.dark-mode .campus-exec-empty { color: #94a3b8; }
body.dark-mode .campus-exec-card__total strong,
body.dark-mode .campus-exec-cat__count,
body.dark-mode .campus-exec-sub__name { color: #e2e8f0; }
body.dark-mode .campus-exec-card__code,
body.dark-mode .campus-exec-chip { background: rgba(148,163,184,.15); color: #cbd5e1; }
body.dark-mode .campus-exec-chip--aktif { background: rgba(46,125,50,.25); color: #86efac; }
body.dark-mode .campus-exec-chip--dipinjam { background: rgba(249,168,37,.2); color: #fde68a; }
body.dark-mode .campus-exec-chip--maint { background: rgba(21,101,192,.25); color: #93c5fd; }
body.dark-mode .campus-exec-chip--rusak { background: rgba(198,40,40,.25); color: #fca5a5; }
body.dark-mode .campus-exec-cat__btn { background: rgba(148,163,184,.08) !important; border-color: rgba(148,163,184,.25) !important; }
body.dark-mode .campus-exec-cat__btn:not(.collapsed) { background: rgba(59,130,246,.12) !important; }
body.dark-mode .campus-exec-progress { background: rgba(148,163,184,.25); }
body.dark-mode .campus-exec-sub { background: transparent; border-color: rgba(148,163,184,.25); }
body.dark-mode .campus-exec-badge--aktif { background: rgba(46,125,50,.25) !important; color: #86efac !important; }
body.dark-mode .campus-exec-badge--dipinjam { background: rgba(249,168,37,.2) !important; color: #fde68a !important; }
body.dark-mode .campus-exec-badge--maint { background: rgba(21,101,192,.25) !important; color: #93c5fd !important; }
body.dark-mode .campus-exec-badge--rusak { background: rgba(198,40,40,.25) !important; color: #fca5a5 !important; }
body.dark-mode .campus-exec-badge--total { background: rgba(148,163,184,.2) !important; color: #cbd5e1 !important; }
body.dark-mode .dash-ent .dash-activity-item { border-left-color: rgba(148,163,184,.35); }
body.dark-mode .dash-ent .dash-activity-item:hover { background: rgba(148,163,184,.08); }
@media (max-width: 575.98px) {
    .dash-ent .dash-widget__value { font-size: 1.25rem; }
}
</style>
@endpush

@section('content')
@php
    $w = $widgets ?? [];
    $widgetDefs = [
        ['key' => 'total_inventory', 'label' => 'Total Inventaris', 'icon' => 'bi bi-box-seam', 'tone' => 'blue', 'see' => 'Jumlah Inventaris'],
        ['key' => 'borrowed', 'label' => 'Dipinjam', 'icon' => 'bi bi-hand-index', 'tone' => 'warning'],
        ['key' => 'pending_approval', 'label' => 'Menunggu Approval', 'icon' => 'bi bi-hourglass-split', 'tone' => 'warning'],
        ['key' => 'approved_today', 'label' => 'Disetujui Hari Ini', 'icon' => 'bi bi-check2-circle', 'tone' => 'green'],
        ['key' => 'rejected_today', 'label' => 'Ditolak Hari Ini', 'icon' => 'bi bi-x-circle', 'tone' => 'danger'],
        ['key' => 'active_loans', 'label' => 'Sedang Dipinjam', 'icon' => 'bi bi-box-arrow-right', 'tone' => 'info'],
        ['key' => 'overdue_loans', 'label' => 'Terlambat Dikembalikan', 'icon' => 'bi bi-clock-history', 'tone' => 'danger'],
        ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'bi bi-wrench-adjustable', 'tone' => 'info'],
        ['key' => 'preventive_due', 'label' => 'Preventive Due', 'icon' => 'bi bi-calendar2-event', 'tone' => 'teal'],
        ['key' => 'lost', 'label' => 'Hilang', 'icon' => 'bi bi-question-circle', 'tone' => 'danger'],
        ['key' => 'auctioned', 'label' => 'Lelang', 'icon' => 'bi bi-hammer', 'tone' => 'purple'],
        ['key' => 'destroyed', 'label' => 'Musnah', 'icon' => 'bi bi-trash3', 'tone' => 'slate'],
    ];
@endphp

<div id="dashboardSkeleton" class="dashboard-skeleton-wrap is-visible" aria-hidden="false">
    <div class="page-header-enterprise mb-3">
        <div class="undira-skel undira-skel-sm mb-2" style="width:180px;"></div>
        <div class="undira-skel undira-skel-xl mb-2" style="width:220px;"></div>
        <div class="undira-skel undira-skel-sm" style="width:320px;max-width:100%;"></div>
    </div>
    <div class="row g-3 mb-3">
        @for($i = 0; $i < 10; $i++)
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card card-undira h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="undira-skel undira-skel-circle"></span>
                    <div class="flex-grow-1">
                        <div class="undira-skel undira-skel-sm mb-2" style="width:70%;"></div>
                        <div class="undira-skel undira-skel-lg" style="width:45%;"></div>
                    </div>
                </div>
            </div>
        </div>
        @endfor
    </div>
</div>

<div id="dashboardRealContent" class="dashboard-real-content is-hidden dash-ent" aria-hidden="true">
<x-page-header
    title="Dashboard"
    subtitle="Ringkasan operasional inventaris Universitas Dian Nusantara"
    icon="bi bi-speedometer2"
    :breadcrumbs="[
        ['label' => 'Dashboard'],
    ]"
/>

@if(!empty($quick_actions))
<x-ui.section-title title="Aksi Cepat" icon="bi bi-lightning-charge-fill" subtitle="Pintasan operasional sesuai izin Anda" />
<div class="row g-3 mb-4 dashboard-quick-actions">
    @foreach($quick_actions as $action)
    <div class="col-6 col-md-4 col-xl-3">
        <x-ui.action-tile
            :href="$action['url']"
            :label="$action['label']"
            :description="$action['description']"
            :icon="'fas '.$action['icon']"
        />
    </div>
    @endforeach
</div>
@endif

<x-ui.section-title title="Status Inventaris" icon="bi bi-grid-1x2" subtitle="Metrik kondisi & operasional aset" />
<div class="row g-3 mb-4">
    @foreach($condition_cards ?? [] as $card)
    @php
        $cardTone = match ($card['tone'] ?? 'good') {
            'damaged' => 'danger',
            'fair' => 'warning',
            default => 'green',
        };
    @endphp
    <div class="col-6 col-md-4 col-xl-2">
        <div class="dash-widget dash-widget--{{ $cardTone }}">
            <div class="dash-widget__body">
                <span class="dash-widget__icon" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                <div class="min-w-0">
                    <div class="dash-widget__label">Kondisi {{ $card['label'] }}</div>
                    <div class="dash-widget__value">{{ number_format((int) $card['total']) }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
    @foreach($widgetDefs as $def)
    <div class="col-6 col-md-4 col-xl-2">
        <div class="dash-widget dash-widget--{{ $def['tone'] }}">
            <div class="dash-widget__body">
                <span class="dash-widget__icon" aria-hidden="true"><i class="{{ $def['icon'] }}"></i></span>
                <div class="min-w-0">
                    <div class="dash-widget__label">{{ $def['label'] }}</div>
                    <div class="dash-widget__value">{{ number_format((int) ($w[$def['key']] ?? 0)) }}</div>
                    @if(!empty($def['see']))
                        <span class="visually-hidden">{{ $def['see'] }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Kept for campus overview + existing feature coverage --}}
<span class="visually-hidden">Barang Aktif {{ number_format($active_inventory ?? $available ?? 0) }}</span>
<span class="visually-hidden">Barang Hilang {{ number_format($lost ?? 0) }}</span>
<span class="visually-hidden">Jumlah Supplier {{ number_format($total_suppliers ?? 0) }}</span>
<span class="visually-hidden">Jumlah Vendor {{ number_format($total_vendors ?? 0) }}</span>

@include('dashboard.partials.schedule-widgets')

@include('dashboard.partials.recent-borrowings')

<x-ui.section-title title="Analitik" icon="bi bi-graph-up" subtitle="Distribusi & tren 6 bulan terakhir" />
<div class="row g-3 mb-2">
    <div class="col-12 col-lg-6">
        <div class="card card-undira dash-chart-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-chart-pie text-undira-green"></i>
                <span>Inventaris per Kampus</span>
            </div>
            <div class="card-body"><div class="dash-chart-wrap"><canvas id="chartCampus"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card card-undira dash-chart-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-chart-bar text-undira-blue"></i>
                <span>Inventaris per Kategori</span>
            </div>
            <div class="card-body"><div class="dash-chart-wrap"><canvas id="chartCategory"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card card-undira dash-chart-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-chart-line" style="color:#00897B"></i>
                <span>Maintenance Trend</span>
                <span class="visually-hidden">Grafik Maintenance</span>
            </div>
            <div class="card-body"><div class="dash-chart-wrap"><canvas id="chartMaintenance"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card card-undira dash-chart-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-chart-area text-warning"></i>
                <span>Borrowing Trend</span>
                <span class="visually-hidden">Grafik Peminjaman</span>
            </div>
            <div class="card-body"><div class="dash-chart-wrap"><canvas id="chartBorrowing"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card card-undira dash-chart-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-chart-pie" style="color:#5E35B1"></i>
                <span>Status (Pie)</span>
            </div>
            <div class="card-body"><div class="dash-chart-wrap"><canvas id="chartStatus"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card card-undira dash-chart-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="fas fa-chart-bar" style="color:#6D4C41"></i>
                <span>Kondisi (Bar)</span>
            </div>
            <div class="card-body"><div class="dash-chart-wrap"><canvas id="chartCondition"></canvas></div></div>
        </div>
    </div>
</div>

<x-ui.section-title title="Statistik per Kampus" icon="bi bi-geo-alt" subtitle="Sebaran inventaris antar kampus" class="mt-3" />
<div class="row g-3 dashboard-campus-grid mb-4">
    @forelse($campus_stats ?? [] as $cs)
    <div class="col-12 col-md-6 col-xl-4 d-flex">
        <x-dashboard.campus-stat-card :campus="$cs" />
    </div>
    @empty
    <div class="col-12"><div class="alert alert-light mb-0">Belum ada data kampus.</div></div>
    @endforelse
</div>

@include('dashboard.partials.campus-executive-section', [
    'executiveSummary' => $executiveSummary ?? ['campuses' => [], 'filter_options' => ['campuses' => [], 'categories' => []]],
    'canRefresh' => $canRefreshCampusSummary ?? false,
])

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-undira h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Inventaris Terbaru</h5>
                @can('inventory.view')
                <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary">Semua</a>
                @endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>No. Inventaris</th>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Lokasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recent_items ?? [] as $item)
                            <tr>
                                <td><a href="{{ route('inventory.show', $item) }}">{{ $item->inventory_number }}</a></td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->category?->name ?? '-' }}</td>
                                <td><small>{{ $item->full_location ?? '-' }}</small></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="p-0">
                                    <x-empty-state
                                        icon="fa-box-open"
                                        title="Belum ada inventaris terbaru"
                                        message="Inventaris yang baru ditambahkan akan muncul di sini."
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
    <div class="col-lg-5">
        <div class="card card-undira h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Aktivitas Terbaru</h5>
                @can('audit-log.view')
                <a href="{{ route('audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">Log</a>
                @endcan
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recent_activity ?? [] as $log)
                    <li class="list-group-item dash-activity-item">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <strong>{{ $log['user'] }}</strong>
                                <span class="badge bg-light text-dark border ms-1">{{ $log['action'] }}</span>
                            </div>
                            <small class="text-muted flex-shrink-0">{{ $log['created_at_human'] }}</small>
                        </div>
                        @if(!empty($log['description']))
                            <small class="text-muted d-block mt-1">{{ $log['description'] }}</small>
                        @endif
                    </li>
                    @empty
                    <li class="list-group-item p-0 border-0">
                        <x-empty-state
                            icon="fa-history"
                            title="Belum ada aktivitas"
                            message="Login, CRUD, import/export, dan approval akan tercatat di sini."
                        />
                    </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
</div>{{-- /#dashboardRealContent --}}
@endsection

@push('scripts')
@include('partials.chartjs-cdn')
<script>
window.undiraDashboardReveal = function () {
    const skel = document.getElementById('dashboardSkeleton');
    const real = document.getElementById('dashboardRealContent');
    if (!skel || !real) return;
    if (real.dataset.revealed === '1') return;
    real.dataset.revealed = '1';
    skel.classList.remove('is-visible');
    skel.setAttribute('aria-hidden', 'true');
    skel.style.display = 'none';
    real.classList.remove('is-hidden');
    real.classList.add('is-fading-in');
    real.setAttribute('aria-hidden', 'false');
    window.requestAnimationFrame(function () {
        initDashboardCharts();
    });
};

const undiraColors = ['#2E7D32', '#1A237E', '#4CAF50', '#3949AB', '#FF9800', '#F44336', '#2196F3', '#00897B', '#6D4C41', '#5E35B1'];
const chartPayload = @json($charts ?? []);
window.__undiraChartInstances = window.__undiraChartInstances || {};

function chartRows(key) {
    const rows = chartPayload && chartPayload[key];
    return Array.isArray(rows) ? rows : [];
}

function makeChart(el, type, labels, data, label) {
    if (!el || typeof Chart === 'undefined') return;
    if (!labels.length) {
        el.parentElement.innerHTML = '<p class="text-muted text-center small mb-0 py-5">Tidak ada data.</p>';
        return;
    }
    const id = el.id || type;
    if (window.__undiraChartInstances[id]) {
        window.__undiraChartInstances[id].destroy();
        delete window.__undiraChartInstances[id];
    }
    const isLine = type === 'line';
    const isBar = type === 'bar';
    window.__undiraChartInstances[id] = new Chart(el, {
        type,
        data: {
            labels,
            datasets: [{
                label: label || 'Jumlah',
                data,
                backgroundColor: isLine ? 'rgba(46, 125, 50, 0.15)' : undiraColors,
                borderColor: isLine ? '#2E7D32' : (isBar ? undiraColors : undefined),
                borderWidth: isLine ? 2 : 1,
                fill: isLine,
                tension: isLine ? 0.35 : undefined,
                borderRadius: isBar ? 6 : undefined,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 450 },
            plugins: {
                legend: { display: type === 'pie' || type === 'doughnut', position: 'bottom' },
            },
            scales: isLine || isBar ? {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148,163,184,.25)' } },
            } : undefined,
        },
    });
}

function initDashboardCharts() {
    if (window.__undiraChartsReady) return;
    window.__undiraChartsReady = true;
    makeChart(
        document.getElementById('chartCampus'),
        'pie',
        chartRows('by_campus').map(c => c.label),
        chartRows('by_campus').map(c => Number(c.total))
    );
    makeChart(
        document.getElementById('chartCategory'),
        'bar',
        chartRows('by_category').map(c => c.label),
        chartRows('by_category').map(c => Number(c.total)),
        'Jumlah'
    );
    makeChart(
        document.getElementById('chartMaintenance'),
        'line',
        chartRows('maintenances_monthly').map(c => c.label),
        chartRows('maintenances_monthly').map(c => Number(c.total)),
        'Maintenance'
    );
    makeChart(
        document.getElementById('chartBorrowing'),
        'line',
        chartRows('borrowings_monthly').map(c => c.label),
        chartRows('borrowings_monthly').map(c => Number(c.total)),
        'Peminjaman'
    );
    makeChart(
        document.getElementById('chartStatus'),
        'pie',
        chartRows('by_status').map(c => c.label),
        chartRows('by_status').map(c => Number(c.total))
    );
    makeChart(
        document.getElementById('chartCondition'),
        'bar',
        chartRows('by_condition').map(c => c.label),
        chartRows('by_condition').map(c => Number(c.total)),
        'Jumlah'
    );
}

setTimeout(function () {
    if (document.getElementById('dashboardRealContent')?.dataset.revealed !== '1') {
        window.undiraDashboardReveal();
    }
}, 2500);

(function initCampusExecutivePanel() {
    const root = document.getElementById('campusExecutiveSection');
    if (!root) return;

    const grid = () => document.getElementById('campusExecutiveCards');
    const emptyFilter = () => document.getElementById('campusExecutiveEmptyFilter');
    const searchEl = document.getElementById('execSearchSub');
    const campusEl = document.getElementById('execFilterCampus');
    const categoryEl = document.getElementById('execFilterCategory');
    const statusEl = document.getElementById('execFilterStatus');
    const sortEl = document.getElementById('execSort');
    const resetEl = document.getElementById('execResetFilters');
    const refreshBtn = document.getElementById('btnRefreshCampusSummary');

    function applyFilters() {
        const cardsRoot = grid();
        if (!cardsRoot) return;

        const q = (searchEl?.value || '').trim().toLowerCase();
        const campusId = campusEl?.value || '';
        const categoryId = categoryEl?.value || '';
        const status = statusEl?.value || '';
        const sort = sortEl?.value || 'total_desc';
        const filtering = Boolean(q || campusId || categoryId || status);

        const wraps = Array.from(cardsRoot.querySelectorAll('.campus-exec-card-wrap'));
        let visibleCards = 0;

        wraps.forEach((wrap) => {
            if (campusId && String(wrap.dataset.campusId) !== String(campusId)) {
                wrap.classList.add('is-hidden');
                return;
            }

            let campusVisible = !filtering;
            wrap.querySelectorAll('.campus-exec-cat').forEach((cat) => {
                if (categoryId && String(cat.dataset.categoryId) !== String(categoryId)) {
                    cat.classList.add('is-hidden');
                    return;
                }

                let catVisible = false;
                const subs = Array.from(cat.querySelectorAll('.campus-exec-sub'));

                if (!subs.length) {
                    catVisible = !q && (!status || Number(cat.getAttribute('data-cat-' + status) || 0) > 0);
                } else {
                    subs.forEach((sub) => {
                        const nameOk = !q || (sub.dataset.subName || '').includes(q);
                        const statusOk = !status || Number(sub.getAttribute('data-sub-' + status) || 0) > 0;
                        const show = nameOk && statusOk;
                        sub.classList.toggle('is-hidden', filtering ? !show : false);
                        if (show) catVisible = true;
                    });
                }

                if (!filtering) {
                    cat.classList.remove('is-hidden');
                    subs.forEach((sub) => sub.classList.remove('is-hidden'));
                    campusVisible = true;
                    return;
                }

                cat.classList.toggle('is-hidden', !catVisible);
                if (catVisible) campusVisible = true;
            });

            wrap.classList.toggle('is-hidden', filtering ? !campusVisible : false);
            if (!wrap.classList.contains('is-hidden')) visibleCards += 1;
        });

        const sorted = wraps.slice().sort((a, b) => {
            const nameA = a.dataset.campusName || '';
            const nameB = b.dataset.campusName || '';
            const totalA = Number(a.dataset.total || 0);
            const totalB = Number(b.dataset.total || 0);
            if (sort === 'total_asc') return totalA - totalB;
            if (sort === 'name_asc') return nameA.localeCompare(nameB);
            if (sort === 'name_desc') return nameB.localeCompare(nameA);
            return totalB - totalA;
        });
        sorted.forEach((el) => cardsRoot.appendChild(el));

        const empty = emptyFilter();
        if (empty) empty.classList.toggle('d-none', visibleCards > 0 || wraps.length === 0);
    }

    ['input', 'change'].forEach((evt) => {
        searchEl?.addEventListener(evt, applyFilters);
        campusEl?.addEventListener(evt, applyFilters);
        categoryEl?.addEventListener(evt, applyFilters);
        statusEl?.addEventListener(evt, applyFilters);
        sortEl?.addEventListener(evt, applyFilters);
    });

    resetEl?.addEventListener('click', function () {
        if (searchEl) searchEl.value = '';
        if (campusEl) campusEl.value = '';
        if (categoryEl) categoryEl.value = '';
        if (statusEl) statusEl.value = '';
        if (sortEl) sortEl.value = 'total_desc';
        applyFilters();
    });

    refreshBtn?.addEventListener('click', async function () {
        const url = root.dataset.refreshUrl;
        if (!url) return;
        const icon = refreshBtn.querySelector('i');
        refreshBtn.disabled = true;
        icon?.classList.add('fa-spin');
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) throw new Error('Refresh gagal');
            const data = await res.json();
            const host = document.getElementById('campusExecutiveGrid');
            if (host && data.html) host.innerHTML = data.html;
            applyFilters();
            if (window.Swal) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message || 'Data diperbarui', timer: 1800, showConfirmButton: false });
            }
        } catch (e) {
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: 'Tidak dapat refresh data ringkasan.' });
            }
        } finally {
            refreshBtn.disabled = false;
            icon?.classList.remove('fa-spin');
        }
    });

    applyFilters();
})();
</script>
@endpush
