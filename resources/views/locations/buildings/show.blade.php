@extends('layouts.app')

@section('title', $building->name . ' - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('campuses.index') }}">Jelajahi Lokasi</a></li>
    <li class="breadcrumb-item"><a href="{{ route('campuses.show', $building->campus) }}">{{ $building->campus->name }}</a></li>
    <li class="breadcrumb-item active">{{ $building->name }}</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-building me-2"></i>{{ $building->name }}</h1>
    <p class="text-muted mb-0">{{ $building->campus->name }} &middot; {{ $building->code }}</p>
</div>

<div class="row">
    @forelse($building->floors as $floor)
    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
        <a href="{{ route('floors.show', $floor) }}" class="text-decoration-none">
            <div class="card location-card card-undira h-100 text-center">
                <div class="card-body py-4">
                    <i class="fas fa-layer-group fa-2x text-undira-blue mb-3"></i>
                    <h5 class="text-undira-blue">{{ $floor->name }}</h5>
                    <p class="text-muted small mb-2">Lantai {{ $floor->floor_number }}</p>
                    <span class="badge bg-undira-green">{{ $floor->rooms_count }} Ruangan</span>
                </div>
            </div>
        </a>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info">Belum ada lantai di gedung ini.</div>
    </div>
    @endforelse
</div>
@endsection
