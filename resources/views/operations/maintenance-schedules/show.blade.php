@extends('layouts.app')

@section('title', 'Detail Jadwal - Inventaris UNDIRA')

@section('content')
@php
    $item = $schedule->inventoryItem;
    $room = $item?->room;
    $floor = $room?->floor;
    $building = $floor?->building;
    $campus = $building?->campus;
@endphp
<x-page-header
    title="Jadwal #{{ $schedule->id }}"
    subtitle="{{ $item?->inventory_number }} · {{ $item?->name }}"
    icon="bi bi-calendar2-check"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Jadwal Preventif', 'url' => route('maintenance-schedules.index')],
        ['label' => '#'.$schedule->id],
    ]"
    :back-route="route('maintenance-schedules.index')"
    back-label="Jadwal Preventif"
>
    @can('maintenance.update')
    <a href="{{ route('maintenance-schedules.edit', $schedule) }}" class="btn btn-outline-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
    <form action="{{ route('maintenance-schedules.advance', $schedule) }}" method="POST" class="d-inline" onsubmit="return confirm('Tandai siklus selesai dan geser tanggal berikutnya?');">
        @csrf
        <button class="btn btn-undira btn-sm"><i class="bi bi-check2-circle me-1"></i>Selesai &amp; Geser Jadwal</button>
    </form>
    @endcan
    @can('maintenance.create')
    <a href="{{ route('maintenances.create', [
        'inventory_item_id' => $schedule->inventory_item_id,
        'maintenance_type' => $schedule->maintenance_type?->value,
        'from_schedule' => $schedule->id,
    ]) }}" class="btn btn-outline-info btn-sm">Buat Work Order</a>
    @endcan
</x-page-header>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-undira mb-3">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Jenis</dt><dd class="col-sm-8">{{ $schedule->maintenance_type?->label() }}</dd>
                    <dt class="col-sm-4">Maintenance Interval</dt><dd class="col-sm-8">{{ $schedule->intervalLabel() }}</dd>
                    <dt class="col-sm-4">Tanggal berikutnya</dt>
                    <dd class="col-sm-8">
                        <strong>{{ $schedule->next_maintenance_date?->format('d/m/Y') }}</strong>
                        @if($schedule->isOverdue())
                            <span class="badge bg-danger ms-1">Terlambat</span>
                        @elseif($schedule->isDueToday())
                            <span class="badge bg-warning text-dark ms-1">Hari ini</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">Reminder</dt><dd class="col-sm-8">{{ $schedule->remind_before_days }} hari sebelumnya (mulai {{ $schedule->reminderStartsAt()->format('d/m/Y') }})</dd>
                    <dt class="col-sm-4">PIC</dt><dd class="col-sm-8">{{ $schedule->responsible_pic ?: '—' }}</dd>
                    <dt class="col-sm-4">Last Maintenance</dt>
                    <dd class="col-sm-8">
                        @if($lastMaintenance)
                            <a href="{{ $lastMaintenance['url'] }}">{{ $lastMaintenance['maintenance_number'] }}</a>
                            <div class="small text-muted">{{ $lastMaintenance['service_date'] }} · {{ $lastMaintenance['type_label'] }} · {{ $lastMaintenance['status_label'] }}</div>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4">Terakhir selesai (siklus)</dt><dd class="col-sm-8">{{ $schedule->last_completed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $schedule->is_active ? 'Aktif' : 'Nonaktif' }}</dd>
                    <dt class="col-sm-4">Catatan</dt><dd class="col-sm-8">{{ $schedule->notes ?: '—' }}</dd>
                    <dt class="col-sm-4">Sumber</dt><dd class="col-sm-8">Preventive Maintenance</dd>
                </dl>
            </div>
            @can('maintenance.delete')
            <div class="card-footer">
                <form action="{{ route('maintenance-schedules.destroy', $schedule) }}" method="POST" onsubmit="return confirm('Hapus jadwal ini?');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm">Hapus Jadwal</button>
                </form>
            </div>
            @endcan
        </div>

        <div class="card card-undira">
            <div class="card-header"><h5 class="mb-0">Timeline Maintenance</h5></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    @forelse($timeline as $ev)
                        <li class="d-flex gap-2 border-bottom py-2">
                            <span class="badge bg-{{ $ev['tone'] ?? 'secondary' }} align-self-start">{{ $ev['date'] ?? '' }}</span>
                            <div>
                                <div class="fw-semibold">
                                    @if(!empty($ev['url']))
                                        <a href="{{ $ev['url'] }}">{{ $ev['title'] }}</a>
                                    @else
                                        {{ $ev['title'] }}
                                    @endif
                                </div>
                                <div class="small text-muted">{{ $ev['subtitle'] ?? '' }} @if(!empty($ev['meta']))· {{ $ev['meta'] }}@endif</div>
                            </div>
                        </li>
                    @empty
                        <li class="text-muted">Belum ada peristiwa.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-undira mb-3">
            <div class="card-header"><h5 class="mb-0">Lokasi Inventaris</h5></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Kampus</dt>
                    <dd class="col-sm-8 fw-semibold">{{ $campus?->name ?: '—' }}</dd>
                    <dt class="col-sm-4">Gedung</dt>
                    <dd class="col-sm-8">{{ $building?->name ?: ($building?->code ?: '—') }}</dd>
                    <dt class="col-sm-4">Lantai</dt>
                    <dd class="col-sm-8">{{ $floor?->name ?: ($floor?->code ?: '—') }}</dd>
                    <dt class="col-sm-4">Ruangan</dt>
                    <dd class="col-sm-8">{{ $room?->name ?: ($room?->code ?: '—') }}</dd>
                </dl>
            </div>
        </div>
        <div class="card card-undira">
            <div class="card-header"><h5 class="mb-0">Inventaris</h5></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Nomor</dt>
                    <dd class="col-sm-8"><code>{{ $item?->inventory_number ?: '—' }}</code></dd>
                    <dt class="col-sm-4">Nama</dt>
                    <dd class="col-sm-8 fw-semibold">{{ $item?->name ?: '—' }}</dd>
                </dl>
                @can('inventory.view')
                <a href="{{ route('inventory.show', $schedule->inventory_item_id) }}" class="btn btn-outline-primary btn-sm mt-3 w-100">Detail Inventaris</a>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection
