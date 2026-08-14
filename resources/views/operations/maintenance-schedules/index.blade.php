@extends('layouts.app')

@section('title', 'Jadwal Pemeliharaan - Inventaris UNDIRA')

@section('content')
@php
    $cardQuery = $filterQuery ?? [];
@endphp
<x-page-header
    title="Jadwal Pemeliharaan Preventif"
    subtitle="Monitoring jadwal preventif per kampus — interval + pengingat Notification Center"
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
        <a href="{{ route('maintenance-schedules.index', array_merge($cardQuery, ['filter' => 'due_today'])) }}" class="text-decoration-none">
            <div class="card card-undira h-100 {{ $filter === 'due_today' ? 'border-primary' : '' }}">
                <div class="card-body">
                    <div class="text-muted small">Jatuh Tempo Hari Ini</div>
                    <div class="fs-3 fw-bold text-primary">{{ $summary['due_today'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('maintenance-schedules.index', array_merge($cardQuery, ['filter' => 'due_week'])) }}" class="text-decoration-none">
            <div class="card card-undira h-100 {{ $filter === 'due_week' ? 'border-info' : '' }}">
                <div class="card-body">
                    <div class="text-muted small">Jatuh Tempo Minggu Ini</div>
                    <div class="fs-3 fw-bold text-info">{{ $summary['due_this_week'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('maintenance-schedules.index', array_merge($cardQuery, ['filter' => 'overdue'])) }}" class="text-decoration-none">
            <div class="card card-undira h-100 {{ $filter === 'overdue' ? 'border-danger' : '' }}">
                <div class="card-body">
                    <div class="text-muted small">Terlambat (Overdue)</div>
                    <div class="fs-3 fw-bold text-danger">{{ $summary['overdue'] }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('maintenance-schedules.index', array_merge($cardQuery, ['filter' => 'all'])) }}" class="text-decoration-none">
            <div class="card card-undira h-100 {{ $filter === 'all' && ($status ?? '') === '' ? 'border-success' : '' }}">
                <div class="card-body">
                    <div class="text-muted small">Jadwal Aktif</div>
                    <div class="fs-3 fw-bold text-success">{{ $summary['active'] }}</div>
                </div>
            </div>
        </a>
    </div>
</div>

@if(($campusCounts ?? []) !== [])
<div class="card card-undira mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted small fw-semibold me-1">Kampus:</span>
            <a href="{{ route('maintenance-schedules.index', array_merge(collect($cardQuery)->except('campus_id')->all(), ['filter' => $filter])) }}"
               class="btn btn-sm {{ empty($cardQuery['campus_id'] ?? null) ? 'btn-undira' : 'btn-outline-secondary' }}">
                Semua Kampus
                <span class="ms-1 opacity-75">{{ collect($campusCounts)->sum('total') }}</span>
            </a>
            @foreach($campusCounts as $row)
                <a href="{{ route('maintenance-schedules.index', array_merge($cardQuery, ['filter' => $filter, 'campus_id' => $row['id']])) }}"
                   class="btn btn-sm {{ (string) ($cardQuery['campus_id'] ?? '') === (string) $row['id'] ? 'btn-undira' : 'btn-outline-secondary' }}">
                    {{ $row['name'] }}
                    <span class="ms-1 opacity-75">{{ $row['total'] }} Jadwal</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif

<form method="GET" class="card card-undira mb-3" action="{{ route('maintenance-schedules.index') }}">
    <div class="card-body row g-2 align-items-end">
        @if($filter !== 'all')
            <input type="hidden" name="filter" value="{{ $filter }}">
        @endif
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="filter-q">Cari</label>
            <input type="text" id="filter-q" name="q" value="{{ request('q') }}" class="form-control" placeholder="No. inventaris / nama / PIC">
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
            <label class="form-label" for="filter-type">Jenis</label>
            <select id="filter-type" name="type" class="form-select">
                <option value="">Semua</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2">
            <label class="form-label" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="form-select">
                <option value="all" @selected(($status ?? '') === 'all')>Semua</option>
                <option value="active" @selected(($status ?? '') === 'active' || ($status ?? '') === '')>Aktif</option>
                <option value="inactive" @selected(($status ?? '') === 'inactive')>Nonaktif</option>
                <option value="overdue" @selected(($status ?? '') === 'overdue')>Overdue</option>
            </select>
        </div>
        <div class="col-12 col-md-4 col-xl-2">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-undira flex-grow-1">Filter</button>
                @if(request()->hasAny(['q', 'campus_id', 'type', 'status', 'filter']))
                    <a href="{{ route('maintenance-schedules.index') }}" class="btn btn-outline-secondary" title="Reset filter">Reset</a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Inventaris</th>
                        <th>Lokasi Kampus</th>
                        <th>Jenis</th>
                        <th>Interval</th>
                        <th>Berikutnya</th>
                        <th>Pengingat</th>
                        <th>PIC</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $schedule)
                    @php
                        $campusName = $schedule->inventoryItem?->room?->floor?->building?->campus?->name;
                    @endphp
                    <tr class="{{ $schedule->isOverdue() ? 'table-danger' : ($schedule->isDueToday() ? 'table-warning' : '') }}">
                        <td>
                            <code>{{ $schedule->inventoryItem?->inventory_number }}</code><br>
                            <small class="text-muted">{{ $schedule->inventoryItem?->name }}</small>
                        </td>
                        <td>{{ $campusName ?: '—' }}</td>
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
                        <td class="text-nowrap text-end">
                            <a href="{{ route('maintenance-schedules.show', $schedule) }}" class="btn btn-sm btn-outline-primary" title="Detail"><i class="fas fa-eye"></i></a>
                            @can('maintenance.update')
                            <a href="{{ route('maintenance-schedules.edit', $schedule) }}" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada jadwal preventif untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($schedules->hasPages())<div class="card-footer">{{ $schedules->links() }}</div>@endif
</div>
@endsection
