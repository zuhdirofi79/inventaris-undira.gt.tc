@extends('layouts.app')

@section('title', 'Notifikasi - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Notifikasi</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-bell me-2"></i>Notifikasi</h1>
    <form action="{{ route('notifications.read-all') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-check-double me-1"></i>Tandai Semua Dibaca</button>
    </form>
</div>

<div class="card card-undira">
    <ul class="list-group list-group-flush">
        @forelse($notifications as $notification)
        <li class="list-group-item {{ $notification->read_at ? '' : 'bg-light' }}">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    @if(!$notification->read_at)
                        <span class="badge bg-undira-green me-2">Baru</span>
                    @endif
                    <strong>{{ $notification->data['title'] ?? 'Notifikasi' }}</strong>
                    <p class="mb-1 text-muted">{{ $notification->data['message'] ?? json_encode($notification->data) }}</p>
                    <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                </div>
                @if(!$notification->read_at)
                <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success" title="Tandai dibaca"><i class="fas fa-check"></i></button>
                </form>
                @endif
            </div>
        </li>
        @empty
        <li class="list-group-item text-center text-muted py-5">
            <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
            Tidak ada notifikasi.
        </li>
        @endforelse
    </ul>
    @if($notifications->hasPages())
    <div class="card-footer">{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
