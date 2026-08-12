@extends('layouts.app')

@section('title', 'Maintenance - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Pemeliharaan Aset"
    subtitle="Work order terikat inventaris — mulai dari scan QR"
    icon="bi bi-wrench-adjustable"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pemeliharaan Aset'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
>
    @can('maintenance.create')
    <a href="{{ route('maintenances.scan') }}" class="btn btn-undira btn-lg px-4">
        <i class="bi bi-qr-code-scan me-2"></i>Scan Inventory QR Code
    </a>
    <a href="{{ route('maintenances.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plus-lg me-1"></i>Manual</a>
    @endcan
    @can('maintenance.view')
    <a href="{{ route('maintenance-dashboard.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
    <a href="{{ route('maintenance-schedules.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar2-check me-1"></i>Jadwal Preventif</a>
    <a href="{{ route('maintenance-reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-bar-graph me-1"></i>Laporan</a>
    @endcan
</x-page-header>

<form method="GET" class="card card-undira mb-3" action="{{ route('maintenances.index') }}">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="filter-q">Cari</label>
            <input type="text" id="filter-q" name="q" value="{{ request('q') }}" class="form-control" placeholder="No. WO / inventaris / teknisi / vendor">
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-xl-3">
            <label class="form-label" for="filter-campus">Lokasi Kampus</label>
            <select id="filter-campus" name="campus_id" class="form-select">
                <option value="">Semua Kampus</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) request('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2">
            <label class="form-label" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="form-select">
                <option value="">Semua</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2">
            <label class="form-label" for="filter-type">Jenis</label>
            <select id="filter-type" name="type" class="form-select">
                <option value="">Semua</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4 col-xl-2">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-undira flex-grow-1">Filter</button>
                @if(request()->hasAny(['q', 'campus_id', 'status', 'type']))
                    <a href="{{ route('maintenances.index') }}" class="btn btn-outline-secondary" title="Reset filter">Reset</a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>No. WO</th>
                        <th>Inventaris</th>
                        <th>Jenis</th>
                        <th>Teknisi</th>
                        <th>Tgl</th>
                        <th>Prioritas</th>
                        <th>Biaya</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($maintenances as $maintenance)
                    <tr>
                        <td><code>{{ $maintenance->maintenance_number ?? '—' }}</code></td>
                        <td>
                            {{ $maintenance->inventoryItem?->inventory_number }}<br>
                            <small class="text-muted">{{ $maintenance->inventoryItem?->name }}</small>
                        </td>
                        <td>{{ $maintenance->maintenance_type?->label() ?? '—' }}</td>
                        <td>{{ $maintenance->technician ?: '—' }}</td>
                        <td>{{ $maintenance->service_date?->format('d/m/Y') }}</td>
                        <td>{{ $maintenance->priority?->label() ?? '—' }}</td>
                        <td>{{ $maintenance->cost ? 'Rp '.number_format($maintenance->cost, 0, ',', '.') : '—' }}</td>
                        <td>
                            @php
                                $statusBadge = match ($maintenance->status) {
                                    \App\Enums\MaintenanceStatus::Revision => 'bg-warning text-dark',
                                    \App\Enums\MaintenanceStatus::PendingApproval => 'bg-primary',
                                    \App\Enums\MaintenanceStatus::Completed => 'bg-success',
                                    \App\Enums\MaintenanceStatus::Cancelled => 'bg-secondary',
                                    default => 'bg-info text-dark',
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $maintenance->status?->label() }}</span>
                            @if($maintenance->status?->value === 'revision' && filled($maintenance->rejection_reason))
                                <div class="small text-muted mt-1" title="{{ $maintenance->rejection_reason }}">
                                    {{ \Illuminate\Support\Str::limit($maintenance->rejection_reason, 60) }}
                                </div>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('maintenances.show', $maintenance) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            @can('maintenance.update')
                            <a href="{{ route('maintenances.edit', $maintenance) }}" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Belum ada work order. Mulai dengan scan QR inventaris.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($maintenances->hasPages())<div class="card-footer">{{ $maintenances->links() }}</div>@endif
</div>
@endsection
