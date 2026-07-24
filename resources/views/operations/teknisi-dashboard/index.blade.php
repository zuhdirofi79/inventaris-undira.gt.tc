@extends('layouts.app')

@section('title', 'Dashboard Teknisi - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Dashboard Teknisi"
    subtitle="Ringkasan work order, perbaikan, dan jadwal preventif Anda."
    icon="bi bi-speedometer"
    :breadcrumbs="[
        ['label' => 'Dashboard Teknisi'],
    ]"
>
    <a href="{{ route('maintenances.scan') }}" class="btn btn-undira btn-sm"><i class="bi bi-qr-code-scan me-1"></i>Scan QR</a>
    <a href="{{ route('maintenances.index') }}" class="btn btn-outline-secondary btn-sm">Work Order</a>
</x-page-header>

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['label' => 'Work Order Hari Ini', 'value' => $widgets['wo_today'], 'icon' => 'bi-calendar-day', 'tone' => 'primary'],
            ['label' => 'Maintenance Berjalan', 'value' => $widgets['in_progress'], 'icon' => 'bi-tools', 'tone' => 'warning'],
            ['label' => 'Menunggu Approval', 'value' => $widgets['pending_approval'], 'icon' => 'bi-hourglass-split', 'tone' => 'info'],
            ['label' => 'Preventif Bulan Ini', 'value' => $widgets['preventive_month'], 'icon' => 'bi-calendar2-check', 'tone' => 'success'],
            ['label' => 'Barang Rusak', 'value' => $widgets['damaged'], 'icon' => 'bi-exclamation-triangle', 'tone' => 'danger'],
            ['label' => 'Maintenance Selesai', 'value' => $widgets['completed_month'], 'icon' => 'bi-check2-circle', 'tone' => 'secondary'],
        ];
    @endphp
    @foreach($cards as $card)
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-undira h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="text-{{ $card['tone'] }}"><i class="bi {{ $card['icon'] }}"></i></span>
                    <span class="small text-muted">{{ $card['label'] }}</span>
                </div>
                <div class="fs-3 fw-bold">{{ number_format($card['value']) }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card card-undira h-100">
            <div class="card-header"><h5 class="mb-0">Grafik Bulanan</h5></div>
            <div class="card-body">
                <canvas id="teknisiMonthlyChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card card-undira h-100">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">Work Order Terbaru</h5>
                <a href="{{ route('maintenances.index') }}" class="small">Lihat semua</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>WO</th><th>Inventaris</th><th>Status</th><th>Tanggal</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recent as $row)
                        <tr>
                            <td><a href="{{ route('maintenances.show', $row) }}"><code>{{ $row->maintenance_number }}</code></a></td>
                            <td>{{ $row->inventoryItem?->name }}</td>
                            <td><span class="badge bg-secondary">{{ $row->status?->label() }}</span></td>
                            <td>{{ $row->service_date?->format('d/m/Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels = @json($monthly->pluck('label'));
    const data = @json($monthly->pluck('total'));
    const ctx = document.getElementById('teknisiMonthlyChart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Work Order',
                data: data,
                backgroundColor: 'rgba(31, 60, 136, 0.75)',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
})();
</script>
@endpush
