@props([
    'campus',
])

@php
    /** @var array<string, mixed> $campus */
    $categories = collect($campus['categories'] ?? [])->values();
    $preview = $categories->take(3);
    $restCount = max(0, $categories->count() - 3);
    $hasInventory = ((int) ($campus['total_inventory'] ?? 0) + (int) ($campus['total_units'] ?? 0)) > 0;
    $inventoryUrl = route('inventory.campus', $campus['id']);
@endphp

<article class="card card-undira campus-stat-card h-100">
    <header class="campus-stat-card__header">
        <h3 class="campus-stat-card__title">{{ $campus['name'] }}</h3>
        <span class="campus-stat-card__code" title="Kode kampus">{{ $campus['code'] }}</span>
    </header>

    <div class="campus-stat-card__body">
        {{-- Primary inventory --}}
        <div class="campus-stat-card__primary" aria-label="Ringkasan inventaris">
            <div class="campus-stat-metric">
                <span class="campus-stat-metric__label">Total Inventaris</span>
                <span class="campus-stat-metric__value">{{ number_format((int) $campus['total_inventory']) }}</span>
            </div>
            <div class="campus-stat-metric">
                <span class="campus-stat-metric__label">Total Unit</span>
                <span class="campus-stat-metric__value">{{ number_format((int) $campus['total_units']) }}</span>
            </div>
        </div>

        {{-- Location --}}
        <div class="campus-stat-card__locations" aria-label="Statistik lokasi">
            <div class="campus-stat-loc">
                <span class="campus-stat-loc__label"><i class="fas fa-building me-1" aria-hidden="true"></i>Gedung</span>
                <span class="campus-stat-loc__value">{{ number_format((int) $campus['buildings']) }}</span>
            </div>
            <div class="campus-stat-loc">
                <span class="campus-stat-loc__label"><i class="fas fa-layer-group me-1" aria-hidden="true"></i>Lantai</span>
                <span class="campus-stat-loc__value">{{ number_format((int) $campus['floors']) }}</span>
            </div>
            <div class="campus-stat-loc">
                <span class="campus-stat-loc__label"><i class="fas fa-door-open me-1" aria-hidden="true"></i>Ruangan</span>
                <span class="campus-stat-loc__value">{{ number_format((int) $campus['rooms']) }}</span>
            </div>
        </div>

        {{-- Categories (max 3) — count = jumlah unit inventaris per kategori --}}
        <div class="campus-stat-card__categories">
            <div class="campus-stat-section-label">Kategori Inventaris</div>
            @if($preview->isNotEmpty())
                <ul class="campus-stat-cat-list">
                    @foreach($preview as $cat)
                    <li>
                        <span class="campus-stat-cat-list__name">{{ $cat['name'] }}</span>
                        <span class="campus-stat-cat-list__count">{{ number_format((int) $cat['total']) }}</span>
                    </li>
                    @endforeach
                </ul>
                @if($restCount > 0)
                <a href="{{ $inventoryUrl }}" class="campus-stat-cat-more" data-no-loader="1">
                    + {{ $restCount }} kategori lainnya
                </a>
                @endif
            @elseif($hasInventory)
                <p class="campus-stat-empty mb-0">Belum ada kategori inventaris.</p>
            @else
                <p class="campus-stat-empty mb-0">
                    <i class="fas fa-inbox me-1" aria-hidden="true"></i>
                    Belum ada data inventaris pada kampus ini.
                </p>
            @endif
        </div>

        {{-- Status --}}
        <div class="campus-stat-card__status" aria-label="Status operasional">
            <span class="campus-stat-chip campus-stat-chip--aktif">Aktif {{ number_format((int) $campus['available']) }}</span>
            <span class="campus-stat-chip campus-stat-chip--dipinjam">Dipinjam {{ number_format((int) $campus['borrowed']) }}</span>
            <span class="campus-stat-chip campus-stat-chip--maint">Maintenance {{ number_format((int) $campus['maintenance']) }}</span>
            <span class="campus-stat-chip campus-stat-chip--rusak">Rusak {{ number_format((int) $campus['damaged']) }}</span>
        </div>
    </div>

    @can('inventory.view')
    <footer class="campus-stat-card__footer">
        <a href="{{ $inventoryUrl }}"
           class="btn btn-sm btn-outline-success w-100 campus-stat-card__action"
           aria-label="Lihat inventaris {{ $campus['name'] }}">
            Lihat Inventaris Kampus
        </a>
    </footer>
    @endcan
</article>
