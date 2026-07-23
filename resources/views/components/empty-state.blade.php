@props([
    'icon' => 'fa-inbox',
    'title' => 'Belum ada data',
    'message' => null,
])

<div {{ $attributes->class(['text-center text-muted py-4 px-3 undira-empty-state']) }}>
    <div class="undira-empty-state-icon mb-2">
        <i class="fas {{ $icon }}" aria-hidden="true"></i>
    </div>
    <div class="fw-semibold">{{ $title }}</div>
    @if($message)
        <p class="small mb-0 mt-1">{{ $message }}</p>
    @endif
    @unless($slot->isEmpty())
        <div class="mt-3">{{ $slot }}</div>
    @endunless
</div>
