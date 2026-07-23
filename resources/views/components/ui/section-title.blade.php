{{-- Section heading between content blocks --}}
@props([
    'title',
    'icon' => null,
    'subtitle' => null,
])

<div {{ $attributes->class(['ui-section-title', 'd-flex', 'align-items-end', 'justify-content-between', 'flex-wrap', 'gap-2']) }}>
    <div class="min-w-0">
        <h2 class="ui-section-title__heading">
            @if($icon)<i class="{{ $icon }} me-2" aria-hidden="true"></i>@endif
            {{ $title }}
        </h2>
        @if($subtitle)
            <p class="ui-section-title__subtitle mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @if($slot->isNotEmpty())
        <div class="ui-section-title__actions">{{ $slot }}</div>
    @endif
</div>
