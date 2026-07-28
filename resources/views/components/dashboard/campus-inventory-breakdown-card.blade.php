@props([
    'campus',
])

@php
    /** @var array<string, mixed> $campus */
    $categories = collect($campus['categories'] ?? []);
    $hasData = $categories->isNotEmpty();
@endphp

<article class="card card-undira dash-campus-breakdown-card h-100">
    <header class="dash-campus-breakdown-card__header">
        <h3 class="dash-campus-breakdown-card__title">{{ $campus['campus_name'] }}</h3>
        @if(!empty($campus['campus_code']))
            <span class="dash-campus-breakdown-card__code">{{ $campus['campus_code'] }}</span>
        @endif
    </header>

    <div class="dash-campus-breakdown-card__body">
        @if($hasData)
            @foreach($categories as $category)
                <section class="dash-campus-breakdown-cat">
                    <h4 class="dash-campus-breakdown-cat__title">
                        {{ $category['category'] }}
                        <span class="dash-campus-breakdown-cat__total">({{ number_format((int) $category['total']) }} Unit)</span>
                    </h4>
                    <ul class="dash-campus-breakdown-sub list-unstyled mb-0">
                        @foreach($category['subcategories'] as $sub)
                            <li class="dash-campus-breakdown-sub__item">
                                <span class="dash-campus-breakdown-sub__name">{{ $sub['name'] }}</span>
                                <span class="dash-campus-breakdown-sub__dots" aria-hidden="true"></span>
                                <span class="dash-campus-breakdown-sub__qty">
                                    <span class="badge dash-campus-breakdown-badge">{{ number_format((int) $sub['total']) }} Unit</span>
                                    <span class="badge dash-campus-breakdown-badge-status">Aktif</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @else
            <p class="dash-campus-breakdown-empty mb-0">Belum ada data inventaris.</p>
        @endif
    </div>
</article>
