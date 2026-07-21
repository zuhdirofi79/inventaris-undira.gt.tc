@props([
    'status' => null,
])

@php
    /** @var \App\Enums\InventoryStatus|null $status */
    $tone = $status?->badgeTone() ?? 'unknown';
    $label = $status?->label() ?? '—';
@endphp

<span {{ $attributes->class([
    'inventory-status-badge',
    'status-'.$tone,
]) }}>{{ $label }}</span>
