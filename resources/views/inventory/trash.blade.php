@extends('layouts.app')

@section('title', 'Trash Inventaris - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    <li class="breadcrumb-item active">Trash</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-trash me-2"></i>Inventaris Terhapus</h1>
    <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
</div>

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
