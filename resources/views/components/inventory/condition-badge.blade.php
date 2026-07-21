@props([
    'condition' => null,
])

@php
    /** @var \App\Enums\InventoryCondition|null $condition */
    $tone = $condition?->badgeTone() ?? 'unknown';
    $label = $condition?->label() ?? '—';
@endphp

<span {{ $attributes->class([
    'inventory-condition-badge',
    'condition-'.$tone,
]) }}>{{ $label }}</span>
