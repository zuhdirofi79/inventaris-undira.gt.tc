@extends('layouts.app')

@section('title', 'Detail Jadwal - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('maintenance-schedules.index') }}">Jadwal Preventif</a></li>
    <li class="breadcrumb-item active">#{{ $schedule->id }}</li>
@endsection

@section('content')
<x-ui.page-header title="Jadwal #{{ $schedule->id }}" subtitle="{{ $schedule->inventoryItem?->inventory_number }} · {{ $schedule->inventoryItem?->name }}" icon="bi bi-calendar2-check">
    @can('maintenance.update')
    <a href="{{ route('maintenance-schedules.edit', $schedule) }}" class="btn btn-outline-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
    <form action="{{ route('maintenance-schedules.advance', $schedule) }}" method="POST" class="d-inline" onsubmit="return confirm('Tandai siklus selesai dan geser tanggal berikutnya?');">
        @csrf
        <button class="btn btn-undira btn-sm"><i class="bi bi-check2-circle me-1"></i>Selesai &amp; Geser Jadwal</button>
    </form>
    @endcan
    @can('maintenance.create')
    <a href="{{ route('maintenances.create', ['inventory_item_id' => $schedule->inventory_item_id]) }}" class="btn btn-outline-info btn-sm">Buat Work Order</a>
    @endcan
</x-ui.page-header>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-undira">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Jenis</dt><dd class="col-sm-8">{{ $schedule->maintenance_type?->label() }}</dd>
                    <dt class="col-sm-4">Interval</dt><dd class="col-sm-8">{{ $schedule->intervalLabel() }}</dd>
                    <dt class="col-sm-4">Tanggal berikutnya</dt>
                    <dd class="col-sm-8">
                        <strong>{{ $schedule->next_maintenance_date?->format('d/m/Y') }}</strong>
                        @if($schedule->isOverdue())
                            <span class="badge bg-danger ms-1">Terlambat</span>
                        @elseif($schedule->isDueToday())
                            <span class="badge bg-warning text-dark ms-1">Hari ini</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">Pengingat</dt><dd class="col-sm-8">{{ $schedule->remind_before_days }} hari sebelumnya</dd>
                    <dt class="col-sm-4">Mulai diingatkan</dt><dd class="col-sm-8">{{ $schedule->reminderStartsAt()->format('d/m/Y') }}</dd>
                    <dt class="col-sm-4">PIC</dt><dd class="col-sm-8">{{ $schedule->responsible_pic ?: '—' }}</dd>
                    <dt class="col-sm-4">Terakhir selesai</dt><dd class="col-sm-8">{{ $schedule->last_completed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $schedule->is_active ? 'Aktif' : 'Nonaktif' }}</dd>
                    <dt class="col-sm-4">Catatan</dt><dd class="col-sm-8">{{ $schedule->notes ?: '—' }}</dd>
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
    </div>
    <div class="col-lg-5">
        <div class="card card-undira">
            <div class="card-header"><h5 class="mb-0">Inventaris</h5></div>
            <div class="card-body small">
                <div><code>{{ $schedule->inventoryItem?->inventory_number }}</code></div>
                <div class="fw-semibold">{{ $schedule->inventoryItem?->name }}</div>
                <div class="text-muted mt-2">
                    {{ $schedule->inventoryItem?->room?->floor?->building?->campus?->name }}
                    · {{ $schedule->inventoryItem?->room?->name }}
                </div>
                @can('inventory.view')
                <a href="{{ route('inventory.show', $schedule->inventory_item_id) }}" class="btn btn-outline-primary btn-sm mt-3 w-100">Detail Inventaris</a>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection
