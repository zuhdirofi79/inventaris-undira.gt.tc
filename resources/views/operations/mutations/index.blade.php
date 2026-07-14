@extends('layouts.app')

@section('title', 'Mutasi - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Operasi</li>
    <li class="breadcrumb-item active">Mutasi</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-exchange-alt me-2"></i>Mutasi Inventaris</h1>
    @can('mutation.create')
    <a href="{{ route('mutations.create') }}" class="btn btn-undira btn-sm"><i class="fas fa-plus me-1"></i>Tambah</a>
    @endcan
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Inventaris</th><th>Dari</th><th>Ke</th><th>Alasan</th><th>Oleh</th><th>Tanggal</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($mutations as $mutation)
                <tr>
                    <td>{{ $mutation->inventoryItem?->inventory_number }}<br><small>{{ $mutation->inventoryItem?->name }}</small></td>
                    <td><small>{{ $mutation->fromRoom?->full_location ?? '-' }}</small></td>
                    <td><small>{{ $mutation->toRoom?->full_location ?? '-' }}</small></td>
                    <td>{{ Str::limit($mutation->reason, 30) ?? '-' }}</td>
                    <td>{{ $mutation->movedBy?->name }}</td>
                    <td>{{ $mutation->created_at->format('d/m/Y H:i') }}</td>
                    <td><a href="{{ route('mutations.show', $mutation) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada mutasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($mutations->hasPages())<div class="card-footer">{{ $mutations->links() }}</div>@endif
</div>
@endsection
