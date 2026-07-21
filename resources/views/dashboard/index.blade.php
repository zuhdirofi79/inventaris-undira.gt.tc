@extends('layouts.app')

@section('title', 'Dashboard - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
    <p class="text-muted mb-0 small">Pusat informasi inventaris Universitas Dian Nusantara</p>
</div>

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

{{-- Card Summary --}}
<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3">
        <div class="card card-undira card-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-green me-3"><i class="fas fa-boxes"></i></div>
                <div>
                    <div class="text-muted small">Total Inventaris</div>
                    <h3 class="mb-0">{{ number_format($total_inventory ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-university"></i></div>
                <div>
                    <div class="text-muted small">Total Kampus</div>
                    <h3 class="mb-0">{{ number_format($total_campuses ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-building"></i></div>
                <div>
                    <div class="text-muted small">Total Gedung</div>
                    <h3 class="mb-0">{{ number_format($total_buildings ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-door-open"></i></div>
                <div>
                    <div class="text-muted small">Total Ruangan</div>
                    <h3 class="mb-0">{{ number_format($total_rooms ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-undira card-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-green me-3"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <div class="text-muted small">Total Nilai</div>
                    <h3 class="mb-0 fs-5">Rp {{ number_format($total_value ?? 0, 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-undira card-stat stat-warning h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-warning me-3"><i class="fas fa-hand-holding"></i></div>
                <div>
                    <div class="text-muted small">Dipinjam</div>
                    <h3 class="mb-0">{{ number_format($borrowed ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-undira card-stat stat-info h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-wrench"></i></div>
                <div>
                    <div class="text-muted small">Maintenance</div>
                    <h3 class="mb-0">{{ number_format($maintenance ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-undira card-stat stat-danger h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-danger me-3"><i class="fas fa-tools"></i></div>
                <div>
                    <div class="text-muted small">Rusak</div>
                    <h3 class="mb-0">{{ number_format($damaged ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Per Kampus --}}
<h5 class="mb-3 mt-2"><i class="fas fa-university me-2"></i>Statistik per Kampus</h5>
<div class="row">
    @forelse($campus_stats ?? [] as $cs)
    <div class="col-12 col-md-6 col-xl-4 mb-3">
        <div class="card card-undira h-100">
            <div class="card-header bg-undira-green text-white d-flex justify-content-between align-items-center">
                <strong class="text-break">{{ $cs['name'] }}</strong>
                <code class="text-white-50 small">{{ $cs['code'] }}</code>
            </div>
            <div class="card-body">
                <div class="row g-2 small mb-2">
                    <div class="col-6"><span class="text-muted">Inventaris</span><div class="fw-bold">{{ number_format($cs['total_inventory']) }}</div></div>
                    <div class="col-6"><span class="text-muted">Unit</span><div class="fw-bold">{{ number_format($cs['total_units']) }}</div></div>
                    <div class="col-12"><span class="text-muted">Nilai</span><div class="fw-bold">Rp {{ number_format($cs['total_value'], 0, ',', '.') }}</div></div>
                    <div class="col-4"><span class="text-muted">Gedung</span><div class="fw-semibold">{{ $cs['buildings'] }}</div></div>
                    <div class="col-4"><span class="text-muted">Lantai</span><div class="fw-semibold">{{ $cs['floors'] }}</div></div>
                    <div class="col-4"><span class="text-muted">Ruangan</span><div class="fw-semibold">{{ $cs['rooms'] }}</div></div>
                </div>
                @if(!empty($cs['categories']))
                <div class="small border-top pt-2">
                    @foreach($cs['categories'] as $cat)
                        <div class="d-flex justify-content-between">
                            <span>{{ $cat['name'] }}</span>
                            <strong>{{ number_format($cat['total']) }}</strong>
                        </div>
                    @endforeach
                </div>
                @endif
                <hr class="my-2">
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-success">Aktif {{ $cs['available'] }}</span>
                    <span class="badge bg-warning text-dark">Pinjam {{ $cs['borrowed'] }}</span>
                    <span class="badge bg-info text-dark">Maint. {{ $cs['maintenance'] }}</span>
                    <span class="badge bg-danger">Rusak {{ $cs['damaged'] }}</span>
                </div>
            </div>
            @can('inventory.view')
            <div class="card-footer bg-transparent">
                <a href="{{ route('inventory.campus', $cs['id']) }}" class="btn btn-sm btn-outline-success w-100">Lihat Inventaris Kampus</a>
            </div>
            @endcan
        </div>
    </div>
    @empty
    <div class="col-12"><div class="alert alert-light">Belum ada data kampus.</div></div>
    @endforelse
</div>

{{-- Per Sub Klasifikasi --}}
<h5 class="mb-3 mt-3"><i class="fas fa-tags me-2"></i>Statistik per Sub Klasifikasi</h5>
<div class="row">
    @forelse($category_breakdown ?? [] as $block)
    <div class="col-12 col-md-6 col-xl-4 mb-3">
        <div class="card card-undira h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ $block['category'] }}</strong>
                <span class="badge bg-undira-green">{{ number_format($block['total']) }}</span>
            </div>
            <ul class="list-group list-group-flush small">
                @foreach($block['subcategories'] as $sub)
                <li class="list-group-item d-flex justify-content-between">
                    <span>{{ $sub['name'] }}</span>
                    <strong>{{ number_format($sub['total']) }}</strong>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @empty
    <div class="col-12"><div class="alert alert-light">Belum ada data klasifikasi inventaris.</div></div>
    @endforelse
</div>

{{-- Per Kampus + Sub Klasifikasi --}}
<h5 class="mb-3 mt-3"><i class="fas fa-sitemap me-2"></i>Kampus × Sub Klasifikasi</h5>
<div class="row">
    @forelse($campus_subcategory_breakdown ?? [] as $campusBlock)
    <div class="col-12 col-lg-6 mb-3">
        <div class="card card-undira h-100">
            <div class="card-header bg-undira-blue text-white">
                <strong>{{ $campusBlock['campus_name'] }}</strong>
            </div>
            <div class="card-body py-2">
                @forelse($campusBlock['categories'] as $cat)
                <div class="mb-3">
                    <div class="fw-semibold mb-1">{{ $cat['category'] }} <span class="badge bg-secondary">{{ number_format($cat['total']) }}</span></div>
                    <div class="row g-1 small">
                        @foreach($cat['subcategories'] as $sub)
                        <div class="col-6 d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">{{ $sub['name'] }}</span>
                            <strong>{{ number_format($sub['total']) }}</strong>
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
    <div class="col-12"><div class="alert alert-light">Belum ada data kampus × klasifikasi.</div></div>
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
    <div class="col-lg-6 mb-4">
        <div class="card card-undira">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Inventaris per Jenis (Sub Klasifikasi)</h5>
            </div>
            <div class="card-body"><canvas id="chartSubcategory" height="260"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card card-undira">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-coins me-2"></i>Nilai Inventaris per Kampus</h5>
            </div>
            <div class="card-body"><canvas id="chartValueCampus" height="260"></canvas></div>
        </div>
    </div>
</div>

@if($highlightItems->isNotEmpty())
<h5 class="mb-3 mt-2"><i class="fas fa-cubes me-2"></i>Ringkasan Jenis Barang</h5>
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
                            <tr><td colspan="4" class="text-center text-muted">Tidak ada data</td></tr>
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
                        <div class="d-flex justify-content-between">
                            <div><strong>{{ $log['user'] }}</strong> <span class="text-muted">{{ $log['action'] }}</span></div>
                            <small class="text-muted">{{ $log['created_at_human'] }}</small>
                        </div>
                        @if(!empty($log['description']))
                            <small class="text-muted">{{ $log['description'] }}</small>
                        @endif
                    </li>
                    @empty
                    <li class="list-group-item text-muted text-center">Tidak ada aktivitas</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const undiraColors = ['#2E7D32', '#1A237E', '#4CAF50', '#3949AB', '#FF9800', '#F44336', '#2196F3', '#00897B', '#6D4C41'];
const chartPayload = @json($charts ?? []);

function makeChart(el, type, labels, data, label) {
    if (!el) return;
    if (!labels.length) {
        el.parentElement.innerHTML = '<p class="text-muted text-center small mb-0">Belum ada data</p>';
        return;
    }
    new Chart(el, {
        type,
        data: {
            labels,
            datasets: [{
                label: label || 'Jumlah',
                data,
                backgroundColor: undiraColors,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: type !== 'bar' } },
        },
    });
}

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
    document.getElementById('chartValueCampus'),
    'bar',
    (chartPayload.value_by_campus || []).map(c => c.label),
    (chartPayload.value_by_campus || []).map(c => Number(c.total)),
    'Nilai (Rp)'
);

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
    $.get('{{ route('dashboard.tree') }}', function (res) {
        $root.html(renderNodes(res.nodes || [], 0));
    }).fail(function () {
        $root.html('<div class="text-danger small">Gagal memuat pohon hierarki.</div>');
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
    $.get(url, function (res) {
        const depth = $node.parents('.tree-node').length + 1;
        $children.html(renderNodes(res.nodes || [], depth));
        $node.data('loaded', 1);
        $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
    }).fail(function () {
        $children.html('<div class="small text-danger">Gagal memuat.</div>');
    });
});

$('#btnReloadTree').on('click', loadTreeRoot);
loadTreeRoot();
</script>
@endpush
