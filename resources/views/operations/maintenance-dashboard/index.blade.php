@extends('layouts.app')

@section('title', 'Dashboard Maintenance - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Dashboard Pemeliharaan"
    subtitle="Ringkasan work order, jadwal preventif, biaya, dan tren"
    icon="bi bi-speedometer2"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pemeliharaan Aset', 'url' => route('maintenances.index')],
        ['label' => 'Dashboard Maintenance'],
    ]"
    :back-route="route('maintenances.index')"
    back-label="Pemeliharaan Aset"
>
    <a href="{{ route('maintenances.scan') }}" class="btn btn-undira btn-sm"><i class="bi bi-qr-code-scan me-1"></i>Scan QR</a>
    <a href="{{ route('maintenance-schedules.index') }}" class="btn btn-outline-primary btn-sm">Jadwal</a>
    <a href="{{ route('maintenance-reports.index') }}" class="btn btn-outline-secondary btn-sm">Laporan</a>
</x-page-header>

<div class="row g-3 mb-3">
    @php
        $cardDefs = [
            ['Total WO', $cards['total'], 'bi-wrench-adjustable', 'text-primary'],
            ['Menunggu', $cards['waiting'], 'bi-hourglass-split', 'text-secondary'],
            ['Sedang Dikerjakan', $cards['on_progress'], 'bi-gear', 'text-info'],
            ['Selesai Hari Ini', $cards['completed_today'], 'bi-check2-circle', 'text-success'],
            ['Selesai Bulan Ini', $cards['completed_month'], 'bi-calendar-check', 'text-success'],
            ['Preventif Due', $cards['preventive_due'], 'bi-calendar2-event', 'text-warning'],
            ['Jadwal Overdue', $cards['overdue_schedules'], 'bi-exclamation-triangle', 'text-danger'],
            ['Biaya Bulan Ini', 'Rp '.number_format($cards['cost_this_month'], 0, ',', '.'), 'bi-cash-stack', 'text-undira-green'],
            ['Rata-rata Perbaikan', $cards['avg_repair_hours'] !== null ? $cards['avg_repair_hours'].' jam' : '—', 'bi-stopwatch', 'text-dark'],
        ];
    @endphp
    @foreach($cardDefs as [$label, $value, $icon, $tone])
    <div class="col-6 col-md-4 col-xl-3">
        <div class="card card-undira h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi {{ $icon }} fs-3 {{ $tone }}"></i>
                <div>
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-5 fw-bold">{{ $value }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card card-undira h-100">
            <div class="card-header"><h5 class="mb-0">Tren Maintenance Bulanan</h5></div>
            <div class="card-body"><canvas id="chartMonthlyTrend" height="240"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-undira h-100">
            <div class="card-header"><h5 class="mb-0">Biaya per Bulan</h5></div>
            <div class="card-body"><canvas id="chartCostMonthly" height="240"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-header"><h5 class="mb-0">Maintenance per Kategori</h5></div>
            <div class="card-body"><canvas id="chartByCategory" height="240"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-header"><h5 class="mb-0">Maintenance per Kampus</h5></div>
            <div class="card-body"><canvas id="chartByCampus" height="240"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-header"><h5 class="mb-0">Aset Paling Sering Diperbaiki</h5></div>
            <div class="card-body"><canvas id="chartTopAssets" height="240"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">WO Terbaru</h5>
                <a href="{{ route('maintenances.index') }}" class="small">Semua</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>WO</th><th>Aset</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($tables['recent'] as $m)
                            <tr>
                                <td><a href="{{ route('maintenances.show', $m) }}"><code class="small">{{ $m->maintenance_number }}</code></a></td>
                                <td class="small">{{ $m->inventoryItem?->name }}</td>
                                <td class="small">{{ $m->status?->label() }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">Kosong</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">Jadwal Mendatang</h5>
                <a href="{{ route('maintenance-schedules.index') }}" class="small">Semua</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Tanggal</th><th>Aset</th><th>Interval</th></tr></thead>
                        <tbody>
                            @forelse($tables['upcoming'] as $s)
                            <tr>
                                <td class="small">{{ $s->next_maintenance_date?->format('d/m/Y') }}</td>
                                <td class="small"><a href="{{ route('maintenance-schedules.show', $s) }}">{{ $s->inventoryItem?->name }}</a></td>
                                <td class="small">{{ $s->intervalLabel() }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">Kosong</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-undira h-100">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">Jadwal Overdue</h5>
                <a href="{{ route('maintenance-schedules.index', ['filter' => 'overdue']) }}" class="small">Semua</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Tanggal</th><th>Aset</th><th>PIC</th></tr></thead>
                        <tbody>
                            @forelse($tables['overdue'] as $s)
                            <tr class="table-danger">
                                <td class="small">{{ $s->next_maintenance_date?->format('d/m/Y') }}</td>
                                <td class="small"><a href="{{ route('maintenance-schedules.show', $s) }}">{{ $s->inventoryItem?->name }}</a></td>
                                <td class="small">{{ $s->responsible_pic ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">Tidak ada overdue</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-muted">
                Barang rusak: {{ $damaged['rusak'] }} · Tidak dapat diperbaiki: {{ $damaged['tidak_dapat_diperbaiki'] }} · Perlu diganti: {{ $damaged['perlu_penggantian'] }}
                · <a href="{{ route('inventory.damaged.index') }}">Lihat daftar</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const charts = @json($charts);
    const colors = ['#2E7D32','#1565C0','#EF6C00','#6A1B9A','#00838F','#C62828','#455A64','#F9A825','#5D4037','#00695C','#AD1457','#283593'];

    function draw(id, type, series, label) {
        const el = document.getElementById(id);
        if (!el) return;
        const labels = (series || []).map(r => r.label);
        const data = (series || []).map(r => Number(r.total));
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
                    backgroundColor: isLine ? 'rgba(46,125,50,.15)' : colors,
                    borderColor: isLine ? '#2E7D32' : undefined,
                    fill: isLine,
                    tension: 0.25,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: type !== 'bar' } },
                scales: (type === 'bar' || type === 'line') ? { y: { beginAtZero: true } } : undefined,
            },
        });
    }

    draw('chartMonthlyTrend', 'line', charts.monthly_trend, 'WO');
    draw('chartCostMonthly', 'bar', charts.cost_monthly, 'Biaya (Rp)');
    draw('chartByCategory', 'doughnut', charts.by_category);
    draw('chartByCampus', 'pie', charts.by_campus);
    draw('chartTopAssets', 'bar', charts.top_assets, 'Frekuensi');
})();
</script>
@endpush
