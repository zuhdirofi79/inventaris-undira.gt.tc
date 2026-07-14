@extends('layouts.app')

@section('title', 'Detail Audit Log - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('audit-logs.index') }}">Audit Log</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@section('content')
<div class="page-header-undira"><h1><i class="fas fa-history me-2"></i>Detail Audit Log</h1></div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Informasi</h5></div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Waktu</th><td>{{ $auditLog->created_at->format('d/m/Y H:i:s') }}</td></tr>
                    <tr><th>User</th><td>{{ $auditLog->user?->name ?? 'System' }} ({{ $auditLog->user?->email }})</td></tr>
                    <tr><th>Aksi</th><td><span class="badge bg-secondary">{{ $auditLog->action?->label() }}</span></td></tr>
                    <tr><th>Deskripsi</th><td>{{ $auditLog->description ?? '-' }}</td></tr>
                    <tr><th>IP Address</th><td>{{ $auditLog->ip_address ?? '-' }}</td></tr>
                    <tr><th>User Agent</th><td><small>{{ $auditLog->user_agent ?? '-' }}</small></td></tr>
                    <tr><th>Model</th><td>{{ $auditLog->auditable_type ? class_basename($auditLog->auditable_type) . ' #' . $auditLog->auditable_id : '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        @if($auditLog->old_values)
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Nilai Lama</h5></div>
            <div class="card-body"><pre class="small mb-0" style="max-height:300px;overflow:auto;">{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
        </div>
        @endif
        @if($auditLog->new_values)
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Nilai Baru</h5></div>
            <div class="card-body"><pre class="small mb-0" style="max-height:300px;overflow:auto;">{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
        </div>
        @endif
    </div>
</div>
@endsection
