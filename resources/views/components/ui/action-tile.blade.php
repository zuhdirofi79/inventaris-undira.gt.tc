{{-- Soft action tile (quick actions) --}}
@props([
    'href',
    'label',
    'description' => null,
    'icon' => 'bi bi-lightning',
])

<a href="{{ $href }}" {{ $attributes->class(['ui-action-tile', 'quick-action-card']) }}>
    <span class="ui-action-tile__icon quick-action-icon" aria-hidden="true">
        <i class="{{ $icon }}"></i>
    </span>
    <span class="ui-action-tile__text min-w-0">
        <span class="ui-action-tile__label quick-action-title d-block">{{ $label }}</span>
        @if($description)
            <span class="ui-action-tile__desc quick-action-desc d-block">{{ $description }}</span>
        @endif
    </span>
</a>
