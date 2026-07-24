@extends('layouts.app')

@section('title', $campus->name . ' - Inventaris UNDIRA')

@push('styles')
@include('locations.partials.explore-styles')
@endpush

@section('content')
<x-page-header
    :title="$campus->name"
    subtitle="Pilih gedung untuk melanjutkan penelusuran lokasi"
    icon="bi bi-building"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Jelajahi Lokasi', 'url' => route('campuses.index')],
        ['label' => $campus->name],
    ]"
    :back-route="route('campuses.index')"
    back-label="Semua Kampus"
    :list-back="true"
/>

<div class="lex-search-wrap">
    <label class="form-label small mb-1" for="lexBuildingSearch">Cari Gedung</label>
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="search" id="lexBuildingSearch" class="form-control" placeholder="Nama atau kode gedung…" autocomplete="off">
    </div>
</div>

<div class="lex-grid lex-grid--3" id="lexBuildingGrid">
    @forelse($buildings as $building)
        <a
            href="{{ route('buildings.show', $building) }}"
            class="lex-card lex-filter-card"
            style="--lex-accent: {{ $accent }}"
            data-filter-text="{{ strtolower($building->name.' '.$building->code) }}"
        >
            <div class="lex-card__top">
                <span class="lex-card__icon" aria-hidden="true"><i class="bi bi-buildings"></i></span>
                <div class="min-w-0">
                    <h2 class="lex-card__title">{{ $building->name }}</h2>
                    <div class="lex-card__meta">
                        @if($building->code)
                            <span class="lex-badge lex-badge--code">{{ $building->code }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="lex-stats lex-stats--3">
                <div class="lex-stat">
                    <span class="lex-stat__icon"><i class="bi bi-layers"></i></span>
                    <span>
                        <span class="lex-stat__label">Lantai</span>
                        <span class="lex-stat__value">{{ number_format($building->floors_count) }}</span>
                    </span>
                </div>
                <div class="lex-stat">
                    <span class="lex-stat__icon"><i class="bi bi-door-open"></i></span>
                    <span>
                        <span class="lex-stat__label">Ruangan</span>
                        <span class="lex-stat__value">{{ number_format($building->rooms_count) }}</span>
                    </span>
                </div>
                <div class="lex-stat">
                    <span class="lex-stat__icon"><i class="bi bi-box-seam"></i></span>
                    <span>
                        <span class="lex-stat__label">Inventaris</span>
                        <span class="lex-stat__value">{{ number_format($building->inventory_count) }}</span>
                    </span>
                </div>
            </div>
            <span class="lex-cta">Lihat Lantai <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </a>
    @empty
        <div class="lex-empty">Belum ada gedung di kampus ini.</div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('lexBuildingSearch');
    const cards = document.querySelectorAll('#lexBuildingGrid .lex-filter-card');
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
