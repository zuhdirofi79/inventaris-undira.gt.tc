@extends('layouts.app')

@section('title', 'Jadwal Pemeliharaan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Jadwal Pemeliharaan Preventif"
    subtitle="Interval perawatan + pengingat otomatis ke Notification Center"
    icon="bi bi-calendar2-check"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pemeliharaan Aset', 'url' => route('maintenances.index')],
        ['label' => 'Jadwal Preventif'],
    ]"
    :back-route="route('maintenances.index')"
    back-label="Pemeliharaan Aset"
>
    @can('maintenance.create')
    <a href="{{ route('maintenance-schedules.create') }}" class="btn btn-undira btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah Jadwal</a>
    @endcan
    <a href="{{ route('maintenances.index') }}" class="btn btn-outline-secondary btn-sm">Work Order</a>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <a href="{{ route('maintenance-schedules.index', ['filter' => 'due_today']) }}" class="text-decoration-none">
            <div class="card card-undira h-100 {{ $filter === 'due_today' ? 'border-primary' : '' }}">
                <div class="card-body">
                    <div class="text-muted small">Jatuh Tempo Hari Ini</div>
                    <div class="fs-3 fw-bold text-primary">{{ $summary['due_today'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('maintenance-schedules.index', ['filter' => 'due_week']) }}" class="text-decoration-none">
            <div class="card card-undira h-100 {{ $filter === 'due_week' ? 'border-info' : '' }}">
                <div class="card-body">
                    <div class="text-muted small">Jatuh Tempo Minggu Ini</div>
                    <div class="fs-3 fw-bold text-info">{{ $summary['due_this_week'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('maintenance-schedules.index', ['filter' => 'overdue']) }}" class="text-decoration-none">
            <div class="card card-undira h-100 {{ $filter === 'overdue' ? 'border-danger' : '' }}">
                <div class="card-body">
                    <div class="text-muted small">Terlambat (Overdue)</div>
                    <div class="fs-3 fw-bold text-danger">{{ $summary['overdue'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('maintenance-schedules.index', ['filter' => 'all']) }}" class="text-decoration-none">
            <div class="card card-undira h-100 {{ $filter === 'all' ? 'border-success' : '' }}">
                <div class="card-body">
                    <div class="text-muted small">Jadwal Aktif</div>
                    <div class="fs-3 fw-bold text-success">{{ $summary['active'] }}</div>
                </div>
            </div>
        </a>
    </div>
</div>

<form method="GET" class="card card-undira mb-3">
    <div class="card-body row g-2 align-items-end">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <div class="col-md-8">
            <label class="form-label">Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="No. inventaris / nama / PIC">
        </div>
        <div class="col-md-4">
            <button class="btn btn-undira w-100">Filter</button>
        </div>
    </div>
</form>

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Inventaris</th>
                        <th>Jenis</th>
                        <th>Interval</th>
                        <th>Berikutnya</th>
                        <th>Pengingat</th>
                        <th>PIC</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $schedule)
                    <tr class="{{ $schedule->isOverdue() ? 'table-danger' : ($schedule->isDueToday() ? 'table-warning' : '') }}">
                        <td>
                            <code>{{ $schedule->inventoryItem?->inventory_number }}</code><br>
                            <small>{{ $schedule->inventoryItem?->name }}</small>
                        </td>
                        <td>{{ $schedule->maintenance_type?->label() }}</td>
                        <td>{{ $schedule->intervalLabel() }}</td>
                        <td>{{ $schedule->next_maintenance_date?->format('d/m/Y') }}</td>
                        <td>{{ $schedule->remind_before_days }} hari</td>
                        <td>{{ $schedule->responsible_pic ?: '—' }}</td>
                        <td>
                            @if(! $schedule->is_active)
                                <span class="badge bg-secondary">Nonaktif</span>
                            @elseif($schedule->isOverdue())
                                <span class="badge bg-danger">Terlambat</span>
                            @elseif($schedule->isDueToday())
                                <span class="badge bg-warning text-dark">Hari ini</span>
                            @else
                                <span class="badge bg-success">Aktif</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('maintenance-schedules.show', $schedule) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            @can('maintenance.update')
                            <a href="{{ route('maintenance-schedules.edit', $schedule) }}" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada jadwal preventif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($schedules->hasPages())<div class="card-footer">{{ $schedules->links() }}</div>@endif
</div>
@endsection
