@extends('layouts.app')

@section('title', $room->name . ' - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('campuses.index') }}">Jelajahi Lokasi</a></li>
    <li class="breadcrumb-item"><a href="{{ route('campuses.show', $room->floor->building->campus) }}">{{ $room->floor->building->campus->name }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('buildings.show', $room->floor->building) }}">{{ $room->floor->building->name }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('floors.show', $room->floor) }}">{{ $room->floor->name }}</a></li>
    <li class="breadcrumb-item active">{{ $room->name }}</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-start flex-wrap">
    <div>
        <h1><i class="fas fa-door-open me-2"></i>{{ $room->name }}</h1>
        <p class="text-muted mb-0">
            {{ $room->full_location }}
            @if($room->room_type) &middot; <span class="badge bg-undira-blue">{{ $room->room_type }}</span> @endif
        </p>
    </div>
    @canany(['inventory.create', 'category.manage'])
    <a href="{{ route('categories.index', [
            'tab' => 'konfigurasi',
            'create' => 1,
            'room_id' => $room->id,
            'floor_id' => $room->floor_id,
            'building_id' => $room->floor->building_id,
            'campus_id' => $room->floor->building->campus_id,
        ]) }}" class="btn btn-undira btn-sm mt-2">
        <i class="fas fa-sliders-h me-1"></i>Konfigurasi Inventaris
    </a>
    @endcanany
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card card-undira card-stat h-100">
            <div class="card-body">
                <div class="text-muted small">Total Item</div>
                <h3 class="mb-0">{{ number_format($stats['total_items']) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body">
                <div class="text-muted small">Grup Barang</div>
                <h3 class="mb-0">{{ number_format($stats['total_groups']) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-undira card-stat stat-info h-100">
            <div class="card-body">
                <div class="text-muted small">Status</div>
                @forelse($stats['by_status'] as $status => $count)
                    <small class="d-block">{{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}</small>
                @empty
                    <small class="d-block text-muted">Belum ada data</small>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card card-undira">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-boxes me-2"></i>Inventaris di Ruangan</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 room-inventory-table">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Kategori</th>
                        <th>Kondisi</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupedInventory as $group)
                    @php
                        $sample = \App\Models\InventoryItem::find($group->sample_id);
                    @endphp
                    <tr>
                        <td>{{ $group->name }}</td>
                        <td>
                            <a href="{{ route('inventory.group-units', $group->group_key) }}" class="badge bg-undira-green text-decoration-none">
                                {{ $group->unit_count }} unit
                            </a>
                        </td>
                        <td>{{ $group->category?->name ?? '—' }}</td>
                        <td>
                            @if($sample)
                                <x-inventory.condition-badge :condition="$sample->condition" />
                            @else
                                <span class="inventory-condition-badge condition-unknown">—</span>
                            @endif
                        </td>
                        <td>
                            @if($sample)
                                <x-inventory.status-badge :status="$sample->status" />
                            @else
                                <span class="inventory-status-badge status-unknown">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($sample)
                            <a href="{{ route('inventory.show', $sample) }}" class="btn btn-sm btn-outline-primary" aria-label="Lihat {{ $group->name }}">
                                <i class="fas fa-eye"></i>
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <p class="fw-semibold mb-1">Belum ada inventaris di ruangan ini.</p>
                            <p class="mb-2 small">Tambahkan inventaris melalui menu Konfigurasi Inventaris.</p>
                            @canany(['inventory.create', 'category.manage'])
                            <a href="{{ route('categories.index', [
                                'tab' => 'konfigurasi',
                                'create' => 1,
                                'room_id' => $room->id,
                                'floor_id' => $room->floor_id,
                                'building_id' => $room->floor->building_id,
                                'campus_id' => $room->floor->building->campus_id,
                            ]) }}" class="btn btn-sm btn-undira">
                                <i class="fas fa-sliders-h me-1"></i>Buka Konfigurasi Inventaris
                            </a>
                            @endcanany
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
