{{-- ERP page chrome: title + optional subtitle/meta + action slot --}}
@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<div {{ $attributes->class(['ui-page-header', 'page-header-undira']) }}>
    <div class="ui-page-header__main min-w-0">
        <h1 class="ui-page-header__title">
            @if($icon)
                <span class="ui-page-header__icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
            @endif
            <span class="text-break">{{ $title }}</span>
        </h1>
        @isset($meta)
            <div class="ui-page-header__subtitle">{{ $meta }}</div>
        @elseif($subtitle)
            <p class="ui-page-header__subtitle mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @if($slot->isNotEmpty())
        <div class="ui-page-header__actions d-flex flex-wrap gap-2 align-items-center">
            {{ $slot }}
        </div>
    @endif
</div>
