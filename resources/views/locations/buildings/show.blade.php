@extends('layouts.app')

@section('title', $building->name . ' - Inventaris UNDIRA')

@push('styles')
@include('locations.partials.explore-styles')
@endpush

@section('content')
<x-page-header
    :title="$building->name"
    :subtitle="$building->campus->name . ' · Pilih lantai'"
    icon="bi bi-buildings"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Jelajahi Lokasi', 'url' => route('campuses.index')],
        ['label' => $building->campus->name, 'url' => route('campuses.show', $building->campus)],
        ['label' => $building->name],
    ]"
    :back-route="route('campuses.show', $building->campus)"
    back-label="Gedung"
    :list-back="true"
/>

<div class="lex-search-wrap">
    <label class="form-label small mb-1" for="lexFloorSearch">Cari Lantai</label>
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="search" id="lexFloorSearch" class="form-control" placeholder="Nama atau kode lantai…" autocomplete="off">
    </div>
</div>

<div class="lex-grid lex-grid--4" id="lexFloorGrid">
    @forelse($floors as $floor)
        <a
            href="{{ route('floors.show', $floor) }}"
            class="lex-card lex-filter-card"
            style="--lex-accent: #1F3C88"
            data-filter-text="{{ strtolower($floor->name.' '.$floor->code.' '.$floor->floor_number) }}"
        >
            <div class="lex-card__top">
                <span class="lex-card__icon" aria-hidden="true"><i class="bi bi-layers"></i></span>
                <div class="min-w-0">
                    <h2 class="lex-card__title">{{ $floor->name }}</h2>
                    <div class="lex-card__meta">
                        @if($floor->code)
                            <span class="lex-badge lex-badge--code">{{ $floor->code }}</span>
                        @endif
                        <span class="lex-badge lex-badge--soft">Lantai {{ $floor->floor_number }}</span>
                    </div>
                </div>
            </div>
            <div class="lex-stats">
                <div class="lex-stat">
                    <span class="lex-stat__icon"><i class="bi bi-door-open"></i></span>
                    <span>
                        <span class="lex-stat__label">Ruangan</span>
                        <span class="lex-stat__value">{{ number_format($floor->rooms_count) }}</span>
                    </span>
                </div>
                <div class="lex-stat">
                    <span class="lex-stat__icon"><i class="bi bi-box-seam"></i></span>
                    <span>
                        <span class="lex-stat__label">Inventaris</span>
                        <span class="lex-stat__value">{{ number_format($floor->inventory_count) }}</span>
                    </span>
                </div>
            </div>
            <span class="lex-cta">Lihat Ruangan <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </a>
    @empty
        <div class="lex-empty">Belum ada lantai di gedung ini.</div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('lexFloorSearch');
    const cards = document.querySelectorAll('#lexFloorGrid .lex-filter-card');
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
