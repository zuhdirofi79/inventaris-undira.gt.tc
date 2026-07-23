{{-- KPI / metric card for dashboards and summaries --}}
@props([
    'label',
    'value',
    'icon' => 'bi bi-grid',
    'tone' => 'green', // green|blue|warning|danger|info
    'href' => null,
])

@php
    $toneClass = match ($tone) {
        'blue' => 'ui-kpi--blue',
        'warning' => 'ui-kpi--warning',
        'danger' => 'ui-kpi--danger',
        'info' => 'ui-kpi--info',
        default => 'ui-kpi--green',
    };
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->class(['ui-kpi', 'card', 'card-undira', 'card-stat', 'h-100', $toneClass, $href ? 'ui-kpi--link text-decoration-none' : null]) }}
>
    <div class="card-body ui-kpi__body">
        <div class="ui-kpi__icon" aria-hidden="true">
            <i class="{{ $icon }}"></i>
        </div>
        <div class="ui-kpi__meta min-w-0">
            <div class="ui-kpi__label">{{ $label }}</div>
            <div class="ui-kpi__value">{{ $value }}</div>
        </div>
    </div>
</{{ $tag }}>
