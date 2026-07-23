@extends('layouts.app')

@section('title', 'Jelajahi Lokasi - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Jelajahi Lokasi</li>
@endsection

@section('content')
<x-ui.page-header
    title="Jelajahi Lokasi"
    subtitle="Pilih kampus untuk melihat gedung, lantai, dan ruangan"
    icon="bi bi-geo-alt"
/>

<div class="row justify-content-center">
    @forelse($campuses as $campus)
    <div class="col-lg-4 col-md-6 mb-4">
        <a href="{{ route('campuses.show', $campus) }}" class="text-decoration-none">
            <div class="card campus-card card-undira h-100">
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                    <i class="fas fa-university fa-3x mb-3"></i>
                    <h4 class="mb-1">{{ $campus->name }}</h4>
                    <p class="mb-2 opacity-75">{{ $campus->code }}</p>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-building me-1"></i>{{ $campus->buildings_count }} Gedung
                    </span>
                </div>
            </div>
        </a>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info text-center">
            <i class="fas fa-info-circle me-2"></i>Belum ada kampus yang tersedia.
        </div>
    </div>
    @endforelse
</div>
@endsection
