{{-- Standard content panel / card shell --}}
@props([
    'title' => null,
    'icon' => null,
    'flush' => false,
    'toolbar' => null,
])

<div {{ $attributes->class(['ui-panel', 'card', 'card-undira']) }}>
    @if($title || $toolbar || isset($actions))
    <div class="card-header card-header-undira ui-panel__header">
        <div class="ui-panel__title-wrap min-w-0">
            @if($icon)
                <span class="ui-panel__icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
            @endif
            @if($title)
                <h5 class="ui-panel__title mb-0 text-truncate">{{ $title }}</h5>
            @endif
        </div>
        @if(isset($actions) || $toolbar)
            <div class="ui-panel__actions d-flex flex-wrap gap-1">
                {{ $actions ?? $toolbar }}
            </div>
        @endif
    </div>
    @endif

    <div @class(['card-body' => ! $flush, 'p-0' => $flush, 'ui-panel__body'])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="card-footer ui-panel__footer">
            {{ $footer }}
        </div>
    @endisset
</div>
