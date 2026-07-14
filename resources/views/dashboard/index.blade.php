@extends('layouts.app')

@section('title', 'Dashboard - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@php
    use App\Models\Campus;
    use App\Models\Building;
    use App\Models\Room;
    $campusCount = Campus::where('is_active', true)->count();
    $buildingCount = Building::where('is_active', true)->count();
    $roomCount = Room::where('is_active', true)->count();
@endphp

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
</div>

<div class="row">
    <div class="col-lg-3 col-md-6 mb-3">
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
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-university"></i></div>
                <div>
                    <div class="text-muted small">Kampus</div>
                    <h3 class="mb-0">{{ $campusCount }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-building"></i></div>
                <div>
                    <div class="text-muted small">Gedung</div>
                    <h3 class="mb-0">{{ $buildingCount }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-blue me-3"><i class="fas fa-door-open"></i></div>
                <div>
                    <div class="text-muted small">Ruangan</div>
                    <h3 class="mb-0">{{ $roomCount }}</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-undira card-stat h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-green me-3"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="text-muted small">Barang Baik (Aktif)</div>
                    <h3 class="mb-0">{{ number_format($active_inventory ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
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
    <div class="col-lg-3 col-md-6 mb-3">
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
    <div class="col-lg-3 col-md-6 mb-3">
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

<div class="row mb-3">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-undira card-stat stat-warning h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon icon-warning me-3"><i class="fas fa-shield-alt"></i></div>
                <div>
                    <div class="text-muted small">Garansi Habis</div>
                    <h3 class="mb-0">{{ number_format($warranty_expiring ?? 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
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
                <h5 class="card-title mb-0"><i class="fas fa-chart-doughnut me-2"></i>Kondisi & Status</h5>
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

// Chart: per kampus (from recent items aggregation)
let chartCampus, chartCategory, chartCondition;

$.get('{{ route('dashboard.charts') }}', function(data) {
    // By category chart
    const catLabels = data.by_category.map(c => c.label);
    const catData = data.by_category.map(c => c.total);
    chartCategory = new Chart(document.getElementById('chartCategory'), {
        type: 'bar',
        data: {
            labels: catLabels,
            datasets: [{ label: 'Jumlah', data: catData, backgroundColor: undiraColors }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    // By status/condition
    const statusLabels = Object.keys(data.by_status).map(s => s.replace(/_/g, ' '));
    const statusData = Object.values(data.by_status);
    chartCondition = new Chart(document.getElementById('chartCondition'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{ data: statusData, backgroundColor: undiraColors }]
        },
        options: { responsive: true }
    });
});

// Campus chart from recent items
$.get('{{ route('dashboard.recent-items') }}', function(items) {
    const campusMap = {};
    items.forEach(item => {
        const campus = item.room?.floor?.building?.campus?.name || 'Lainnya';
        campusMap[campus] = (campusMap[campus] || 0) + 1;
    });
    chartCampus = new Chart(document.getElementById('chartCampus'), {
        type: 'pie',
        data: {
            labels: Object.keys(campusMap),
            datasets: [{ data: Object.values(campusMap), backgroundColor: undiraColors }]
        },
        options: { responsive: true }
    });

    let rows = '';
    items.forEach(item => {
        const loc = item.full_location || '-';
        rows += `<tr>
            <td><a href="/inventory/${item.id}">${item.inventory_number}</a></td>
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
