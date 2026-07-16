@extends('layouts.app')

@section('title', 'Unit Grup - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    @if($items->isNotEmpty() && $items->first()->category)
    <li class="breadcrumb-item"><a href="{{ route('inventory.category', $items->first()->category) }}">{{ $items->first()->category->name }}</a></li>
    @endif
    <li class="breadcrumb-item active">Unit Grup</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-layer-group me-2"></i>Unit dalam Grup</h1>
    <p class="text-muted mb-0">
        @if($items->isNotEmpty())
            {{ $items->first()->name }} ({{ $items->count() }} unit)
        @endif
    </p>
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>No. Inventaris</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Lokasi</th>
                    <th>Kondisi</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>{{ $item->unit_sequence }}</td>
                    <td><code>{{ $item->inventory_number }}</code></td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->category?->name }}</td>
                    <td><small>{{ $item->full_location }}</small></td>
                    <td><span class="badge bg-info">{{ $item->condition?->label() }}</span></td>
                    <td><span class="badge bg-secondary">{{ $item->status?->label() }}</span></td>
                    <td>
                        <a href="{{ route('inventory.show', $item) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                        @can('inventory.update')
                        <a href="{{ route('inventory.edit', $item) }}" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada unit dalam grup ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
