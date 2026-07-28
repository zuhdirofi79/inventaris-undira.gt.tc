@php
    $campuses = $executiveSummary['campuses'] ?? [];
@endphp

<div class="row g-3 mb-4" id="campusExecutiveCards">
    @forelse($campuses as $campus)
        <div
            class="col-12 col-md-6 col-xl-4 d-flex campus-exec-card-wrap"
            data-campus-id="{{ $campus['campus_id'] }}"
            data-campus-name="{{ Str::lower($campus['campus_name']) }}"
            data-total="{{ (int) $campus['total'] }}"
            data-aktif="{{ (int) $campus['aktif'] }}"
            data-dipinjam="{{ (int) $campus['dipinjam'] }}"
            data-maintenance="{{ (int) $campus['maintenance'] }}"
            data-rusak="{{ (int) $campus['rusak'] }}"
        >
            <article class="card card-undira campus-exec-card h-100 w-100">
                <header class="campus-exec-card__header">
                    <div>
                        <h3 class="campus-exec-card__title">{{ $campus['campus_name'] }}</h3>
                        <div class="campus-exec-card__total">
                            Total Inventaris
                            <strong>{{ number_format((int) $campus['total']) }} Unit</strong>
                        </div>
                    </div>
                    <span class="campus-exec-card__code">{{ $campus['campus_code'] }}</span>
                </header>

                <div class="campus-exec-card__stats">
                    <span class="campus-exec-chip">Kategori {{ number_format((int) $campus['category_count']) }}</span>
                    <span class="campus-exec-chip">Sub {{ number_format((int) $campus['subcategory_count']) }}</span>
                    <span class="campus-exec-chip campus-exec-chip--aktif">Aktif {{ number_format((int) $campus['aktif']) }}</span>
                    <span class="campus-exec-chip campus-exec-chip--dipinjam">Dipinjam {{ number_format((int) $campus['dipinjam']) }}</span>
                    <span class="campus-exec-chip campus-exec-chip--maint">Maintenance {{ number_format((int) $campus['maintenance']) }}</span>
                    <span class="campus-exec-chip campus-exec-chip--rusak">Rusak {{ number_format((int) $campus['rusak']) }}</span>
                </div>

                <div class="campus-exec-card__body">
                    @if(empty($campus['categories']))
                        <p class="campus-exec-empty mb-0">Belum ada inventaris pada kategori ini.</p>
                    @else
                        <div class="accordion accordion-flush campus-exec-accordion" id="execAcc{{ $campus['campus_id'] }}">
                            @foreach($campus['categories'] as $category)
                                @php $accId = 'execCat'.$campus['campus_id'].'_'.$category['category_id']; @endphp
                                <div
                                    class="accordion-item campus-exec-cat"
                                    data-category-id="{{ $category['category_id'] }}"
                                    data-category-name="{{ Str::lower($category['name']) }}"
                                    data-cat-total="{{ (int) $category['total'] }}"
                                    data-cat-aktif="{{ (int) $category['aktif'] }}"
                                    data-cat-dipinjam="{{ (int) $category['dipinjam'] }}"
                                    data-cat-maintenance="{{ (int) $category['maintenance'] }}"
                                    data-cat-rusak="{{ (int) $category['rusak'] }}"
                                >
                                    <h2 class="accordion-header" id="h{{ $accId }}">
                                        <button class="accordion-button collapsed campus-exec-cat__btn" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#{{ $accId }}"
                                            aria-expanded="false" aria-controls="{{ $accId }}">
                                            <div class="w-100 pe-2">
                                                <div class="d-flex justify-content-between align-items-center gap-2">
                                                    <span class="campus-exec-cat__name">{{ $category['name'] }}</span>
                                                    <span class="campus-exec-cat__count">{{ number_format((int) $category['total']) }}</span>
                                                </div>
                                                <div class="campus-exec-progress mt-1" aria-hidden="true">
                                                    <div class="campus-exec-progress__bar" style="width: {{ min(100, (int) $category['share_percent']) }}%"></div>
                                                </div>
                                                <div class="campus-exec-cat__meta">
                                                    <span class="badge campus-exec-badge campus-exec-badge--aktif">Aktif {{ number_format((int) $category['aktif']) }}</span>
                                                    <span class="badge campus-exec-badge campus-exec-badge--dipinjam">Dipinjam {{ number_format((int) $category['dipinjam']) }}</span>
                                                    <span class="badge campus-exec-badge campus-exec-badge--maint">Maintenance {{ number_format((int) $category['maintenance']) }}</span>
                                                    <span class="badge campus-exec-badge campus-exec-badge--rusak">Rusak {{ number_format((int) $category['rusak']) }}</span>
                                                    <span class="badge campus-exec-badge campus-exec-badge--total">Total {{ number_format((int) $category['total']) }}</span>
                                                    <span class="text-muted small ms-1">{{ (int) $category['share_percent'] }}%</span>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="{{ $accId }}" class="accordion-collapse collapse"
                                        data-bs-parent="#execAcc{{ $campus['campus_id'] }}"
                                        aria-labelledby="h{{ $accId }}">
                                        <div class="accordion-body pt-2">
                                            @forelse($category['subcategories'] as $sub)
                                                <div
                                                    class="campus-exec-sub"
                                                    data-sub-name="{{ Str::lower($sub['name']) }}"
                                                    data-sub-aktif="{{ (int) $sub['aktif'] }}"
                                                    data-sub-dipinjam="{{ (int) $sub['dipinjam'] }}"
                                                    data-sub-maintenance="{{ (int) $sub['maintenance'] }}"
                                                    data-sub-rusak="{{ (int) $sub['rusak'] }}"
                                                    data-sub-total="{{ (int) $sub['total'] }}"
                                                >
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        @can('inventory.view')
                                                            <a href="{{ $sub['url'] }}" class="campus-exec-sub__name" data-no-loader="1">{{ $sub['name'] }}</a>
                                                        @else
                                                            <span class="campus-exec-sub__name">{{ $sub['name'] }}</span>
                                                        @endcan
                                                        <span class="badge campus-exec-badge campus-exec-badge--total">Total {{ number_format((int) $sub['total']) }}</span>
                                                    </div>
                                                    <div class="campus-exec-sub__badges">
                                                        <span class="badge campus-exec-badge campus-exec-badge--aktif">Aktif : {{ number_format((int) $sub['aktif']) }}</span>
                                                        <span class="badge campus-exec-badge campus-exec-badge--dipinjam">Dipinjam : {{ number_format((int) $sub['dipinjam']) }}</span>
                                                        <span class="badge campus-exec-badge campus-exec-badge--maint">Maintenance : {{ number_format((int) $sub['maintenance']) }}</span>
                                                        <span class="badge campus-exec-badge campus-exec-badge--rusak">Rusak : {{ number_format((int) $sub['rusak']) }}</span>
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="campus-exec-empty mb-0">Belum ada inventaris pada kategori ini.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
        </div>
    @empty
        <div class="col-12">
            <div class="card card-undira">
                <div class="card-body text-muted">Belum ada data inventaris.</div>
            </div>
        </div>
    @endforelse
</div>

<div id="campusExecutiveEmptyFilter" class="alert alert-light border d-none mb-4">Tidak ada data yang cocok dengan filter/pencarian.</div>
