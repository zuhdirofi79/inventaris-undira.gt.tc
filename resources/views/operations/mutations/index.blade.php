@extends('layouts.app')

@section('title', 'Mutasi - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Mutasi Inventaris"
    subtitle="Pindahkan lokasi inventaris antar ruangan"
    icon="bi bi-arrow-left-right"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Mutasi'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
>
    @can('mutation.create')
    <a href="{{ route('mutations.create') }}" class="btn btn-undira btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah</a>
    @endcan
</x-page-header>

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
