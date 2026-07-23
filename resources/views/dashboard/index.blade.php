@extends('layouts.app')

@section('title', 'Dashboard - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div id="dashboardSkeleton" class="dashboard-skeleton-wrap is-visible" aria-hidden="false">
    <div class="page-header-undira mb-3">
        <div class="undira-skel undira-skel-xl mb-2" style="width:220px;"></div>
        <div class="undira-skel undira-skel-sm" style="width:320px;max-width:100%;"></div>
    </div>
    <div class="row g-3 mb-3">
        @for($i = 0; $i < 4; $i++)
        <div class="col-6 col-lg-3">
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
    <div class="row g-3 mb-3">
        @for($i = 0; $i < 8; $i++)
        <div class="col-6 col-lg-3">
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
    <div class="row g-3">
        @for($i = 0; $i < 3; $i++)
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card card-undira">
                <div class="card-header"><div class="undira-skel undira-skel-md" style="width:55%;"></div></div>
                <div class="card-body">
                    <div class="undira-skel undira-skel-sm mb-2" style="width:100%;"></div>
                    <div class="undira-skel undira-skel-sm mb-2" style="width:90%;"></div>
                    <div class="undira-skel undira-skel-sm mb-2" style="width:80%;"></div>
                    <div class="undira-skel undira-skel-sm" style="width:60%;"></div>
                </div>
            </div>
        </div>
        @endfor
    </div>
</div>

<div id="dashboardRealContent" class="dashboard-real-content is-hidden" aria-hidden="true">
<x-ui.page-header
    title="Dashboard"
    subtitle="Pusat informasi inventaris Universitas Dian Nusantara"
    icon="bi bi-speedometer2"
/>

@php
    $highlightKeys = ['Kursi', 'Meja', 'Lemari', 'Komputer', 'Laptop', 'Printer', 'AC', 'LCD', 'Whiteboard', 'Proyektor', 'Monitor'];
    $subMap = collect($category_breakdown ?? [])
        ->flatMap(fn ($block) => $block['subcategories'] ?? [])
        ->keyBy(fn ($s) => strtolower($s['name']));
    $highlightItems = collect($highlightKeys)->map(function ($name) use ($subMap) {
        $row = $subMap->get(strtolower($name));
        return ['name' => $name, 'total' => (int) ($row['total'] ?? 0)];
    })->filter(fn ($i) => $i['total'] > 0)->values();
    if ($highlightItems->isEmpty()) {
        $highlightItems = collect($category_breakdown ?? [])
            ->flatMap(fn ($block) => collect($block['subcategories'] ?? [])->map(fn ($s) => [
                'name' => $s['name'],
                'total' => (int) $s['total'],
            ]))
            ->sortByDesc('total')
            ->take(8)
            ->values();
    }
@endphp

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

{{-- Card Summary --}}
<x-ui.section-title title="Ringkasan Inventaris" icon="bi bi-speedometer2" subtitle="Metrik utama aset UNDIRA" class="mt-1" />
<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Jumlah Inventaris" :value="number_format($total_inventory ?? 0)" icon="bi bi-box-seam" tone="green" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Barang Aktif" :value="number_format($active_inventory ?? $available ?? 0)" icon="bi bi-check-circle" tone="green" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Barang Dipinjam" :value="number_format($borrowed ?? 0)" icon="bi bi-hand-index" tone="warning" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Barang Maintenance" :value="number_format($maintenance ?? 0)" icon="bi bi-wrench-adjustable" tone="info" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Barang Rusak" :value="number_format($damaged ?? 0)" icon="bi bi-exclamation-triangle" tone="danger" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Barang Hilang" :value="number_format($lost ?? 0)" icon="bi bi-question-circle" tone="danger" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Jumlah Supplier" :value="number_format($total_suppliers ?? 0)" icon="bi bi-truck" tone="blue" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Jumlah Vendor" :value="number_format($total_vendors ?? 0)" icon="bi bi-shop" tone="blue" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Jumlah Kampus" :value="number_format($total_campuses ?? 0)" icon="bi bi-building" tone="blue" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Total Gedung" :value="number_format($total_buildings ?? 0)" icon="bi bi-buildings" tone="blue" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Total Ruangan" :value="number_format($total_rooms ?? 0)" icon="bi bi-door-open" tone="blue" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Total Lantai" :value="number_format($total_floors ?? 0)" icon="bi bi-layers" tone="green" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Total Unit Kerja" :value="number_format($total_org_units ?? 0)" icon="bi bi-people" tone="blue" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Total Kategori" :value="number_format($total_categories ?? 0)" icon="bi bi-tags" tone="green" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Total Subkategori" :value="number_format($total_sub_categories ?? 0)" icon="bi bi-tag" tone="green" />
    </div>
    <div class="col-6 col-lg-3">
        <x-ui.kpi label="Total User" :value="number_format($total_users ?? 0)" icon="bi bi-person-badge" tone="blue" />
    </div>
</div>

{{-- Per Kampus --}}
<x-ui.section-title title="Statistik per Kampus" icon="bi bi-geo-alt" subtitle="Sebaran inventaris antar kampus" />
<div class="row g-3 dashboard-campus-grid">
    @forelse($campus_stats ?? [] as $cs)
    <div class="col-12 col-md-6 col-xl-4 d-flex">
        <x-dashboard.campus-stat-card :campus="$cs" />
    </div>
    @empty
    <div class="col-12"><div class="alert alert-light mb-0">Belum ada data kampus.</div></div>
    @endforelse
</div>

{{-- Per Sub Klasifikasi --}}
<x-ui.section-title title="Statistik per Sub Klasifikasi" icon="bi bi-tags" class="mt-4" />
<div class="row g-3 align-items-start">
    @forelse($category_breakdown ?? [] as $block)
    @php
        $subs = collect($block['subcategories'] ?? []);
        $subPreview = $subs->take(8);
        $subRest = $subs->slice(8);
        $subCollapseId = 'cat-subs-'.($block['category_id'] ?? md5($block['category']));
    @endphp
    <div class="col-12 col-sm-12 col-md-6 col-lg-4">
        <div class="card card-undira dashboard-info-card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <strong class="text-break">{{ $block['category'] }}</strong>
                <span class="badge bg-undira-green flex-shrink-0">{{ number_format($block['total']) }}</span>
            </div>
            <ul class="list-group list-group-flush small">
                @foreach($subPreview as $sub)
                <li class="list-group-item d-flex justify-content-between gap-2">
                    <span class="text-break">{{ $sub['name'] }}</span>
                    <strong class="flex-shrink-0">{{ number_format($sub['total']) }}</strong>
                </li>
                @endforeach
            </ul>
            @if($subRest->isNotEmpty())
            <div class="collapse" id="{{ $subCollapseId }}">
                <ul class="list-group list-group-flush small border-top-0">
                    @foreach($subRest as $sub)
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span class="text-break">{{ $sub['name'] }}</span>
                        <strong class="flex-shrink-0">{{ number_format($sub['total']) }}</strong>
                    </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-footer bg-transparent py-2">
                <button
                    class="btn btn-link btn-sm px-0 text-undira-green campus-cat-toggle"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#{{ $subCollapseId }}"
                    aria-expanded="false"
                    aria-controls="{{ $subCollapseId }}"
                >
                    Tampilkan {{ $subRest->count() }} sub kategori lainnya
                </button>
            </div>
            @endif
        </div>
    </div>
    @empty
    <div class="col-12"><div class="alert alert-light mb-0">Belum ada data klasifikasi inventaris.</div></div>
    @endforelse
</div>

{{-- Per Kampus + Sub Klasifikasi --}}
<x-ui.section-title title="Kampus × Sub Klasifikasi" icon="bi bi-diagram-3" class="mt-4" />
<div class="row g-3 align-items-start">
    @forelse($campus_subcategory_breakdown ?? [] as $campusBlock)
    <div class="col-12 col-lg-6">
        <div class="card card-undira dashboard-info-card">
            <div class="card-header bg-undira-blue text-white">
                <strong class="text-break">{{ $campusBlock['campus_name'] }}</strong>
            </div>
            <div class="card-body py-2">
                @forelse($campusBlock['categories'] as $cat)
                <div class="mb-3">
                    <div class="fw-semibold mb-1 text-break">
                        {{ $cat['category'] }}
                        <span class="badge bg-secondary">{{ number_format($cat['total']) }}</span>
                    </div>
                    <div class="row g-1 small">
                        @foreach($cat['subcategories'] as $sub)
                        <div class="col-12 col-sm-6 d-flex justify-content-between border-bottom py-1 gap-2">
                            <span class="text-muted text-break">{{ $sub['name'] }}</span>
                            <strong class="flex-shrink-0">{{ number_format($sub['total']) }}</strong>
                        </div>
                        @endforeach
                    </div>
                </div>
                @empty
                <p class="text-muted small mb-0">Belum ada inventaris di kampus ini.</p>
                @endforelse
            </div>
        </div>
    </div>
    @empty
    <div class="col-12"><div class="alert alert-light mb-0">Belum ada data kampus × klasifikasi.</div></div>
    @endforelse
</div>

<div class="card card-undira mt-2 mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Navigasi Hierarki Inventaris</h5>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnReloadTree">
            <i class="fas fa-sync-alt me-1"></i>Muat Ulang
        </button>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">Kampus → Gedung → Lantai → Ruangan → Kategori → Item. Klik baris untuk expand.</p>
        <div id="inventoryTree" class="inventory-tree">
            <div class="text-muted small">Memuat pohon...</div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row mt-2">
    <div class="col-lg-4 mb-4">
        <div class="card card-undira">
            <div class="card-header bg-undira-green text-white">
                <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>Inventaris per Kampus</h5>
            </div>
            <div class="card-body"><canvas id="chartCampus" height="250"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card card-undira">
            <div class="card-header bg-undira-blue text-white">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Inventaris per Kategori</h5>
            </div>
            <div class="card-body"><canvas id="chartCategory" height="250"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card card-undira">
            <div class="card-header" style="background:#3949AB;color:#fff;">
                <h5 class="card-title mb-0"><i class="fas fa-chart-doughnut me-2"></i>Inventaris per Status</h5>
            </div>
            <div class="card-body"><canvas id="chartStatus" height="250"></canvas></div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card card-undira">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Inventaris per Jenis</h5>
            </div>
            <div class="card-body"><canvas id="chartSubcategory" height="260"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card card-undira">
            <div class="card-header" style="background:#6D4C41;color:#fff;">
                <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>Inventaris per Kondisi</h5>
            </div>
            <div class="card-body"><canvas id="chartCondition" height="260"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card card-undira">
            <div class="card-header" style="background:#455A64;color:#fff;">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Inventaris per Unit Kerja</h5>
            </div>
            <div class="card-body"><canvas id="chartOrgUnit" height="260"></canvas></div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card card-undira">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2"></i>Grafik Peminjaman</h5>
            </div>
            <div class="card-body"><canvas id="chartBorrowing" height="260"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card card-undira">
            <div class="card-header" style="background:#00897B;color:#fff;">
                <h5 class="card-title mb-0"><i class="fas fa-chart-area me-2"></i>Grafik Maintenance</h5>
            </div>
            <div class="card-body"><canvas id="chartMaintenance" height="260"></canvas></div>
        </div>
    </div>
</div>

@if($highlightItems->isNotEmpty())
<x-ui.section-title title="Ringkasan Jenis Barang" icon="bi bi-grid-3x3-gap" class="mt-2" />
<div class="row g-3 mb-3">
    @foreach($highlightItems as $hi)
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card card-undira highlight-item-card h-100">
            <div class="card-body py-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-undira-blue">{{ $hi['name'] }}</span>
                <span class="badge bg-undira-green rounded-pill fs-6">{{ number_format($hi['total']) }}</span>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card card-undira">
            <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-box me-2"></i>Inventaris Terbaru</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
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
    <div class="col-lg-5 mb-4">
        <div class="card card-undira">
            <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Aktivitas Terbaru</h5></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recent_activity ?? [] as $log)
                    <li class="list-group-item">
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
    // Chart.js butuh canvas visible
    window.requestAnimationFrame(function () {
        initDashboardCharts();
        loadTreeRoot();
    });
};

const undiraColors = ['#2E7D32', '#1A237E', '#4CAF50', '#3949AB', '#FF9800', '#F44336', '#2196F3', '#00897B', '#6D4C41'];
const chartPayload = @json($charts ?? []);

function makeChart(el, type, labels, data, label) {
    if (!el) return;
    if (!labels.length) {
        el.parentElement.innerHTML = '<p class="text-muted text-center small mb-0">Belum ada data</p>';
        return;
    }
    const isLine = type === 'line';
    new Chart(el, {
        type,
        data: {
            labels,
            datasets: [{
                label: label || 'Jumlah',
                data,
                backgroundColor: isLine ? 'rgba(46, 125, 50, 0.15)' : undiraColors,
                borderColor: isLine ? '#2E7D32' : undefined,
                borderWidth: isLine ? 2 : undefined,
                fill: isLine,
                tension: isLine ? 0.3 : undefined,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: type === 'pie' || type === 'doughnut' } },
            scales: isLine || type === 'bar' ? {
                y: { beginAtZero: true, ticks: { precision: 0 } },
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
        (chartPayload.by_campus || []).map(c => Number(c.total)),
    );
    makeChart(
        document.getElementById('chartCategory'),
        'bar',
        (chartPayload.by_category || []).map(c => c.label),
        (chartPayload.by_category || []).map(c => Number(c.total)),
        'Jumlah'
    );
    makeChart(
        document.getElementById('chartStatus'),
        'doughnut',
        (chartPayload.by_status || []).map(c => c.label),
        (chartPayload.by_status || []).map(c => Number(c.total)),
    );
    makeChart(
        document.getElementById('chartSubcategory'),
        'bar',
        (chartPayload.by_subcategory || []).map(c => c.label),
        (chartPayload.by_subcategory || []).map(c => Number(c.total)),
        'Jumlah'
    );
    makeChart(
        document.getElementById('chartCondition'),
        'doughnut',
        (chartPayload.by_condition || []).map(c => c.label),
        (chartPayload.by_condition || []).map(c => Number(c.total)),
    );
    makeChart(
        document.getElementById('chartOrgUnit'),
        'bar',
        (chartPayload.by_org_unit || []).map(c => c.label),
        (chartPayload.by_org_unit || []).map(c => Number(c.total)),
        'Jumlah'
    );
    makeChart(
        document.getElementById('chartBorrowing'),
        'line',
        (chartPayload.borrowings_monthly || []).map(c => c.label),
        (chartPayload.borrowings_monthly || []).map(c => Number(c.total)),
        'Peminjaman'
    );
    makeChart(
        document.getElementById('chartMaintenance'),
        'line',
        (chartPayload.maintenances_monthly || []).map(c => c.label),
        (chartPayload.maintenances_monthly || []).map(c => Number(c.total)),
        'Maintenance'
    );
}

function statsBadges(stats) {
    if (!stats) return '';
    return `<span class="badge bg-secondary">Total ${stats.total ?? 0}</span>
        <span class="badge bg-warning text-dark">Pinjam ${stats.borrowed ?? 0}</span>
        <span class="badge bg-info text-dark">Maint ${stats.maintenance ?? 0}</span>
        <span class="badge bg-danger">Rusak ${stats.damaged ?? 0}</span>`;
}

function renderNodes(nodes, depth) {
    let html = '<ul class="tree-level list-unstyled mb-0">';
    (nodes || []).forEach(node => {
        const pad = depth * 16;
        if (node.type === 'item') {
            html += `<li class="tree-item" style="padding-left:${pad}px">
                <a href="${node.url}" class="tree-link">
                    <i class="fas fa-box me-1 text-muted"></i>
                    <code class="me-2">${node.code || ''}</code>${node.label || ''}
                    <span class="badge bg-light text-dark border ms-1">${node.status || '-'}</span>
                </a>
            </li>`;
            return;
        }
        html += `<li class="tree-node" data-url="${node.children_url || ''}" data-loaded="0" style="padding-left:${pad}px">
            <div class="tree-row">
                <button type="button" class="btn btn-sm btn-link tree-toggle p-0 me-1" ${node.has_children ? '' : 'disabled'}>
                    <i class="fas fa-chevron-right"></i>
                </button>
                <strong>${node.label}</strong>
                ${node.code ? `<code class="ms-2 small">${node.code}</code>` : ''}
                <span class="ms-2 tree-stats">${statsBadges(node.stats)}</span>
            </div>
            <div class="tree-children d-none"></div>
        </li>`;
    });
    html += '</ul>';
    return html;
}

function loadTreeRoot() {
    const $root = $('#inventoryTree');
    $root.html('<div class="text-muted small">Memuat pohon...</div>');
    $.ajax({
        url: '{{ route('dashboard.tree') }}',
        method: 'GET',
        undiraTarget: '#inventoryTree',
        success: function (res) {
            $root.html(renderNodes(res.nodes || [], 0));
        },
        error: function () {
            $root.html('<div class="text-danger small">Gagal memuat pohon hierarki.</div>');
        }
    });
}

$('#inventoryTree').on('click', '.tree-toggle', function () {
    const $node = $(this).closest('.tree-node');
    const $children = $node.children('.tree-children');
    const $icon = $(this).find('i');
    if ($node.data('loaded') == 1) {
        $children.toggleClass('d-none');
        $icon.toggleClass('fa-chevron-right fa-chevron-down');
        return;
    }
    const url = $node.data('url');
    if (!url) return;
    $children.removeClass('d-none').html('<div class="small text-muted py-1">Memuat...</div>');
    $.ajax({
        url: url,
        method: 'GET',
        undiraTarget: $children[0],
        success: function (res) {
            const depth = $node.parents('.tree-node').length + 1;
            $children.html(renderNodes(res.nodes || [], depth));
            $node.data('loaded', 1);
            $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        },
        error: function () {
            $children.html('<div class="small text-danger">Gagal memuat.</div>');
        }
    });
});

$('#btnReloadTree').on('click', loadTreeRoot);

// Fallback jika undira-loading belum memanggil reveal
setTimeout(function () {
    if (document.getElementById('dashboardRealContent')?.dataset.revealed !== '1') {
        window.undiraDashboardReveal();
    }
}, 2500);

document.querySelectorAll('.campus-cat-toggle').forEach(function (btn) {
    const target = document.querySelector(btn.getAttribute('data-bs-target'));
    if (!target) return;
    const labelMore = btn.textContent.trim();
    const labelLess = 'Sembunyikan';
    target.addEventListener('shown.bs.collapse', function () {
        btn.textContent = labelLess;
        btn.setAttribute('aria-expanded', 'true');
    });
    target.addEventListener('hidden.bs.collapse', function () {
        btn.textContent = labelMore;
        btn.setAttribute('aria-expanded', 'false');
    });
});
</script>
@endpush
