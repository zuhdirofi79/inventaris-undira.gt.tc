@extends('layouts.app')

@section('title', 'Notifikasi - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Notifikasi"
    subtitle="Peringatan dan aktivitas sistem"
    icon="bi bi-bell"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Notifikasi'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
>
    <form action="{{ route('notifications.read-all') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all me-1"></i>Tandai Semua Dibaca</button>
    </form>
</x-page-header>

<div class="card card-undira">
    <ul class="list-group list-group-flush">
        @forelse($notifications as $notification)
            @php($item = \App\Support\NotificationPresenter::forDropdown($notification))
            <li class="list-group-item undira-notif-item-page {{ $item['unread'] ? 'is-unread bg-light' : '' }}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="d-flex gap-3 min-w-0">
                        <span class="undira-notif-icon flex-shrink-0" aria-hidden="true">
                            <i class="{{ $item['icon'] }}"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                @if($item['unread'])
                                    <span class="badge bg-undira-green">Baru</span>
                                @endif
                                <strong class="{{ $item['unread'] ? 'fw-bold' : 'fw-semibold' }}">{{ $item['title'] }}</strong>
                            </div>
                            @if($item['subject'])
                                <div class="small fw-semibold mt-1">{{ $item['subject'] }}</div>
                            @endif
                            @if($item['subtitle'])
                                <div class="small text-muted">{{ $item['subtitle'] }}</div>
                            @endif
                            @if($item['summary'])
                                <p class="mb-1 small text-muted">{{ $item['summary'] }}</p>
                            @elseif($item['message'])
                                <p class="mb-1 small text-muted">{{ $item['message'] }}</p>
                            @endif
                            <small class="text-muted">{{ $item['time_ago'] }}</small>
                            @if(!empty($item['url']) && $item['url'] !== route('notifications.index'))
                                <div class="mt-1">
                                    <a href="{{ url($item['url']) }}" class="small">Buka detail</a>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if($item['unread'])
                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST" class="flex-shrink-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-success" title="Tandai dibaca" aria-label="Tandai notifikasi dibaca">
                            <i class="fas fa-check" aria-hidden="true"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </li>
        @empty
        <li class="list-group-item text-center text-muted py-5">
            <i class="fas fa-bell fa-2x mb-2 d-block opacity-50" aria-hidden="true"></i>
            <div class="fw-semibold text-body">Tidak ada notifikasi</div>
            <div class="small">Semua aktivitas terbaru akan muncul di sini.</div>
        </li>
        @endforelse
    </ul>
    @if($notifications->hasPages())
    <div class="card-footer">{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
