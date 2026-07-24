@extends('layouts.app')

@section('title', 'Audit Log - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Audit Log"
    subtitle="Jejak aktivitas dan perubahan data"
    icon="bi bi-clock-history"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Admin', 'url' => route('audit-logs.index')],
        ['label' => 'Audit Log'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
/>

<div class="filter-panel mb-4">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label small">Aksi</label>
            <select name="action" class="form-select">
                <option value="">Semua</option>
                @foreach(\App\Enums\AuditAction::cases() as $action)
                    <option value="{{ $action->value }}" {{ request('action') == $action->value ? 'selected' : '' }}>{{ $action->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Dari Tanggal</label>
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Sampai Tanggal</label>
            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-undira btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
        </div>
    </form>
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0">
            <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Deskripsi</th><th>Terdampak</th><th>Browser</th><th>IP</th><th></th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td><small>{{ $log->created_at->format('d/m/Y H:i:s') }}</small></td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                    <td><span class="badge bg-secondary">{{ $log->action?->label() ?? $log->action }}</span></td>
                    <td>
                        {{ Str::limit($log->description, 40) ?? '-' }}
                        @if($log->reason)<br><small class="text-muted">Alasan: {{ $log->reason }}</small>@endif
                    </td>
                    <td>{{ $log->affected_count ?? '-' }}</td>
                    <td><small>{{ $log->browser }} / {{ $log->device }}</small></td>
                    <td><small>{{ $log->ip_address ?? '-' }}</small></td>
                    <td><a href="{{ route('audit-logs.show', $log) }}" class="btn btn-xs btn-outline-primary btn-sm"><i class="fas fa-eye"></i></a></td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada log.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="card-footer">{{ $logs->links() }}</div>@endif
</div>
@endsection
