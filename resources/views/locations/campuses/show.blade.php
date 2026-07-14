@extends('layouts.app')

@section('title', $campus->name . ' - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('campuses.index') }}">Jelajahi Lokasi</a></li>
    <li class="breadcrumb-item active">{{ $campus->name }}</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-university me-2"></i>{{ $campus->name }}</h1>
    @if($campus->description)
        <p class="text-muted mb-0">{{ $campus->description }}</p>
    @endif
</div>

<div class="row">
    @forelse($campus->buildings as $building)
    <div class="col-lg-4 col-md-6 mb-4">
        <a href="{{ route('buildings.show', $building) }}" class="text-decoration-none">
            <div class="card location-card card-undira h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div class="stat-icon icon-blue me-3"><i class="fas fa-building"></i></div>
                        <div>
                            <h5 class="card-title text-undira-blue mb-1">{{ $building->name }}</h5>
                            <p class="text-muted small mb-2">{{ $building->code }}</p>
                            <span class="badge bg-undira-green">
                                <i class="fas fa-layer-group me-1"></i>{{ $building->floors_count }} Lantai
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info">Belum ada gedung di kampus ini.</div>
    </div>
    @endforelse
</div>
@endsection
