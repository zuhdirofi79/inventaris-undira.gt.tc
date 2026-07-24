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
        ['key' => 'good_condition', 'label' => 'Kondisi Baik', 'icon' => 'bi bi-check2-circle', 'tone' => 'green'],
        ['key' => 'minor_damage', 'label' => 'Rusak Ringan', 'icon' => 'bi bi-exclamation-circle', 'tone' => 'warning'],
        ['key' => 'major_damage', 'label' => 'Rusak Berat', 'icon' => 'bi bi-exclamation-triangle', 'tone' => 'danger'],
        ['key' => 'borrowed', 'label' => 'Dipinjam', 'icon' => 'bi bi-hand-index', 'tone' => 'warning'],
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

function makeChart(el, type, labels, data, label) {
    if (!el) return;
    if (!labels.length) {
        el.parentElement.innerHTML = '<p class="text-muted text-center small mb-0 py-5">Belum ada data</p>';
        return;
    }
    const isLine = type === 'line';
    const isBar = type === 'bar';
    new Chart(el, {
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
        (chartPayload.by_campus || []).map(c => c.label),
        (chartPayload.by_campus || []).map(c => Number(c.total))
    );
    makeChart(
        document.getElementById('chartCategory'),
        'bar',
        (chartPayload.by_category || []).map(c => c.label),
        (chartPayload.by_category || []).map(c => Number(c.total)),
        'Jumlah'
    );
    makeChart(
        document.getElementById('chartMaintenance'),
        'line',
        (chartPayload.maintenances_monthly || []).map(c => c.label),
        (chartPayload.maintenances_monthly || []).map(c => Number(c.total)),
        'Maintenance'
    );
    makeChart(
        document.getElementById('chartBorrowing'),
        'line',
        (chartPayload.borrowings_monthly || []).map(c => c.label),
        (chartPayload.borrowings_monthly || []).map(c => Number(c.total)),
        'Peminjaman'
    );
    makeChart(
        document.getElementById('chartStatus'),
        'pie',
        (chartPayload.by_status || []).map(c => c.label),
        (chartPayload.by_status || []).map(c => Number(c.total))
    );
    makeChart(
        document.getElementById('chartCondition'),
        'bar',
        (chartPayload.by_condition || []).map(c => c.label),
        (chartPayload.by_condition || []).map(c => Number(c.total)),
        'Jumlah'
    );
}

setTimeout(function () {
    if (document.getElementById('dashboardRealContent')?.dataset.revealed !== '1') {
        window.undiraDashboardReveal();
    }
}, 2500);
</script>
@endpush
