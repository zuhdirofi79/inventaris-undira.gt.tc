@extends('layouts.app')

@section('title', 'Trash Inventaris - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Inventaris Terhapus"
    subtitle="Data soft-deleted yang masih dapat dipulihkan."
    icon="bi bi-trash"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Data Inventaris', 'url' => route('inventory.index')],
        ['label' => 'Trash'],
    ]"
    :back-route="route('inventory.index')"
    back-label="Data Inventaris"
/>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>No. Inventaris</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Lokasi</th>
                    <th>Dihapus</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>{{ $item->inventory_number }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->category?->name }}</td>
                    <td><small>{{ $item->full_location }}</small></td>
                    <td>{{ $item->deleted_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @can('inventory.restore')
                        <form action="{{ route('inventory.restore', $item->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-undo me-1"></i>Pulihkan</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Trash kosong.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())
    <div class="card-footer">{{ $items->links() }}</div>
    @endif
</div>
@endsection
