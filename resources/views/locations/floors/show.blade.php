@extends('layouts.app')

@section('title', $floor->name . ' - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('campuses.index') }}">Jelajahi Lokasi</a></li>
    <li class="breadcrumb-item"><a href="{{ route('campuses.show', $floor->building->campus) }}">{{ $floor->building->campus->name }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('buildings.show', $floor->building) }}">{{ $floor->building->name }}</a></li>
    <li class="breadcrumb-item active">{{ $floor->name }}</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-layer-group me-2"></i>{{ $floor->name }}</h1>
    <p class="text-muted mb-0">{{ $floor->building->name }} &middot; Lantai {{ $floor->floor_number }}</p>
</div>

<div class="row">
    @forelse($floor->rooms as $room)
    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
        <a href="{{ route('rooms.show', $room) }}" class="text-decoration-none">
            <div class="card location-card card-undira h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <i class="fas fa-door-open fa-lg text-undira-green"></i>
                        @if($room->room_type)
                            <span class="badge bg-undira-blue">{{ $room->room_type }}</span>
                        @endif
                    </div>
                    <h6 class="text-undira-blue mb-1">{{ $room->name }}</h6>
                    <p class="text-muted small mb-2">{{ $room->code }}</p>
                    <span class="badge bg-secondary">
                        <i class="fas fa-box me-1"></i>{{ $room->inventory_items_count }} Item
                    </span>
                    @if($room->capacity)
                        <span class="badge bg-light text-dark ms-1">Kapasitas: {{ $room->capacity }}</span>
                    @endif
                </div>
            </div>
        </a>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info">Belum ada ruangan di lantai ini.</div>
    </div>
    @endforelse
</div>
@endsection
