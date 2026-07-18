@extends('layouts.app')

@section('title', 'Dashboard - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
    <p class="text-muted mb-0 small">Ringkasan inventaris per kampus</p>
</div>

<div class="row">
    <div class="col-6 col-lg-3 mb-3">
        <div class="card card-undira card-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-green me-3"><i class="fas fa-boxes"></i></div>
                <div>
                    <div class="text-muted small">Total Unit</div>
                    <h3 class="mb-0">{{ number_format($total_inventory ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-university"></i></div>
                <div>
                    <div class="text-muted small">Kampus</div>
                    <h3 class="mb-0">{{ number_format($total_campuses ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-building"></i></div>
                <div>
                    <div class="text-muted small">Gedung</div>
                    <h3 class="mb-0">{{ number_format($total_buildings ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-door-open"></i></div>
                <div>
                    <div class="text-muted small">Ruangan</div>
                    <h3 class="mb-0">{{ number_format($total_rooms ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-6 col-lg-3 mb-3">
        <div class="card card-undira card-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-green me-3"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="text-muted small">Tersedia</div>
                    <h3 class="mb-0">{{ number_format($active_inventory ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
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
    <div class="col-6 col-lg-3 mb-3">
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
    <div class="col-6 col-lg-3 mb-3">
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
</div>

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
                <div class="row g-2 small">
                    <div class="col-6"><span class="text-muted">Inventaris</span><div class="fw-bold">{{ number_format($cs['total_inventory']) }}</div></div>
                    <div class="col-6"><span class="text-muted">Unit</span><div class="fw-bold">{{ number_format($cs['total_units']) }}</div></div>
                    <div class="col-4"><span class="text-muted">Gedung</span><div class="fw-semibold">{{ $cs['buildings'] }}</div></div>
                    <div class="col-4"><span class="text-muted">Lantai</span><div class="fw-semibold">{{ $cs['floors'] }}</div></div>
                    <div class="col-4"><span class="text-muted">Ruangan</span><div class="fw-semibold">{{ $cs['rooms'] }}</div></div>
                </div>
                <hr class="my-2">
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-success">Tersedia {{ $cs['available'] }}</span>
                    <span class="badge bg-warning text-dark">Pinjam {{ $cs['borrowed'] }}</span>
                    <span class="badge bg-info text-dark">Maint. {{ $cs['maintenance'] }}</span>
                    <span class="badge bg-danger">Rusak {{ $cs['damaged'] }}</span>
                </div>
            </div>
            @can('inventory.view')
            <div class="card-footer bg-transparent">
                <a href="{{ route('inventory.campus', $cs['id']) }}" class="btn btn-sm btn-outline-success w-100">
                    Lihat Inventaris Kampus
                </a>
            </div>
            @endcan
        </div>
    </div>
    @empty
    <div class="col-12"><div class="alert alert-light">Belum ada data kampus.</div></div>
    @endforelse
</div>

<div class="row mt-2">
    <div class="col-lg-4 mb-4">
        <div class="card card-undira">
            <div class="card-header bg-undira-green text-white">
                <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>Per Kampus</h5>
            </div>
            <div class="card-body">
                <canvas id="chartCampus" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card card-undira">
            <div class="card-header bg-undira-blue text-white">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Per Kategori</h5>
            </div>
            <div class="card-body">
                <canvas id="chartCategory" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card card-undira">
            <div class="card-header" style="background:#3949AB;color:#fff;">
                <h5 class="card-title mb-0"><i class="fas fa-chart-doughnut me-2"></i>Status</h5>
            </div>
            <div class="card-body">
                <canvas id="chartCondition" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card card-undira">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-box me-2"></i>Inventaris Terbaru</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="recentItemsTable">
                        <thead>
                            <tr>
                                <th>No. Inventaris</th>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Lokasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="4"><div class="skeleton skeleton-text"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-4">
        <div class="card card-undira">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Aktivitas Terbaru</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" id="recentActivityList">
                    <li class="list-group-item"><div class="skeleton skeleton-text"></div></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const undiraColors = ['#2E7D32', '#1A237E', '#4CAF50', '#3949AB', '#FF9800', '#F44336', '#2196F3'];

$.get('{{ route('dashboard.charts') }}', function(data) {
    const campusLabels = (data.by_campus || []).map(c => c.label);
    const campusData = (data.by_campus || []).map(c => c.total);
    new Chart(document.getElementById('chartCampus'), {
        type: 'pie',
        data: {
            labels: campusLabels,
            datasets: [{ data: campusData, backgroundColor: undiraColors }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });

    const catLabels = (data.by_category || []).map(c => c.label);
    const catData = (data.by_category || []).map(c => c.total);
    new Chart(document.getElementById('chartCategory'), {
        type: 'bar',
        data: {
            labels: catLabels,
            datasets: [{ label: 'Jumlah', data: catData, backgroundColor: undiraColors }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    const statusLabels = Object.keys(data.by_status || {}).map(s => s.replace(/_/g, ' '));
    const statusData = Object.values(data.by_status || {});
    new Chart(document.getElementById('chartCondition'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{ data: statusData, backgroundColor: undiraColors }]
        },
        options: { responsive: true }
    });
});

$.get('{{ route('dashboard.recent-items') }}', function(items) {
    let rows = '';
    items.forEach(item => {
        const loc = item.full_location || '-';
        rows += `<tr>
            <td><a href="{{ url('/inventory') }}/${item.id}">${item.inventory_number}</a></td>
            <td>${item.name}</td>
            <td>${item.category?.name || '-'}</td>
            <td><small>${loc}</small></td>
        </tr>`;
    });
    $('#recentItemsTable tbody').html(rows || '<tr><td colspan="4" class="text-center text-muted">Tidak ada data</td></tr>');
});

$.get('{{ route('dashboard.recent-activity') }}', function(logs) {
    let html = '';
    logs.forEach(log => {
        const user = log.user?.name || 'System';
        const action = log.action?.label || log.action || '-';
        const time = new Date(log.created_at).toLocaleString('id-ID');
        html += `<li class="list-group-item">
            <div class="d-flex justify-content-between">
                <div><strong>${user}</strong> <span class="text-muted">${action}</span></div>
                <small class="text-muted">${time}</small>
            </div>
            ${log.description ? `<small class="text-muted">${log.description}</small>` : ''}
        </li>`;
    });
    $('#recentActivityList').html(html || '<li class="list-group-item text-muted text-center">Tidak ada aktivitas</li>');
});
</script>
@endpush
