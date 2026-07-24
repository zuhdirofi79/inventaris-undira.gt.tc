@extends('layouts.app')

@section('title', 'Jelajahi Lokasi - Inventaris UNDIRA')

@push('styles')
@include('locations.partials.explore-styles')
@endpush

@section('content')
<x-page-header
    title="Jelajahi Lokasi"
    subtitle="Kampus → Gedung → Lantai → Ruangan → Kategori → Sub Kategori → Inventaris"
    icon="bi bi-geo-alt"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Jelajahi Lokasi'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
/>

<div class="lex-search-wrap">
    <label class="form-label small mb-1" for="lexCampusSearch">Cari Kampus</label>
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="search" id="lexCampusSearch" class="form-control" placeholder="Nama atau kode kampus…" autocomplete="off">
    </div>
</div>

<div class="lex-grid lex-grid--3" id="lexCampusGrid">
    @forelse($campuses as $campus)
        <a
            href="{{ route('campuses.show', $campus['id']) }}"
            class="lex-card lex-filter-card"
            style="--lex-accent: {{ $campus['accent'] }}"
            data-filter-text="{{ strtolower($campus['name'].' '.$campus['code']) }}"
        >
            <div class="lex-card__top">
                <span class="lex-card__icon" aria-hidden="true"><i class="bi bi-building"></i></span>
                <div class="min-w-0">
                    <h2 class="lex-card__title">{{ $campus['name'] }}</h2>
                    <div class="lex-card__meta">
                        @if($campus['code'])
                            <span class="lex-badge lex-badge--code">{{ $campus['code'] }}</span>
                        @endif
                        <span class="lex-badge lex-badge--soft">{{ number_format($campus['unit_count']) }} Inventaris</span>
                    </div>
                </div>
            </div>
            <div class="lex-stats">
                <div class="lex-stat">
                    <span class="lex-stat__icon"><i class="bi bi-buildings"></i></span>
                    <span>
                        <span class="lex-stat__label">Gedung</span>
                        <span class="lex-stat__value">{{ number_format($campus['building_count']) }}</span>
                    </span>
                </div>
                <div class="lex-stat">
                    <span class="lex-stat__icon"><i class="bi bi-layers"></i></span>
                    <span>
                        <span class="lex-stat__label">Lantai</span>
                        <span class="lex-stat__value">{{ number_format($campus['floor_count']) }}</span>
                    </span>
                </div>
                <div class="lex-stat">
                    <span class="lex-stat__icon"><i class="bi bi-door-open"></i></span>
                    <span>
                        <span class="lex-stat__label">Ruangan</span>
                        <span class="lex-stat__value">{{ number_format($campus['room_count']) }}</span>
                    </span>
                </div>
                <div class="lex-stat">
                    <span class="lex-stat__icon"><i class="bi bi-box-seam"></i></span>
                    <span>
                        <span class="lex-stat__label">Inventaris</span>
                        <span class="lex-stat__value">{{ number_format($campus['unit_count']) }}</span>
                    </span>
                </div>
            </div>
            <span class="lex-cta">Lihat Lokasi <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </a>
    @empty
        <div class="lex-empty">Belum ada kampus yang tersedia.</div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('lexCampusSearch');
    const cards = document.querySelectorAll('#lexCampusGrid .lex-filter-card');
    if (!input) return;
    input.addEventListener('input', function () {
        const q = (input.value || '').trim().toLowerCase();
        cards.forEach(function (card) {
            const hay = card.getAttribute('data-filter-text') || '';
            card.classList.toggle('lex-hidden', q !== '' && hay.indexOf(q) === -1);
        });
    });
})();
</script>
@endpush
