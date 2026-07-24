@extends('layouts.app')

@section('title', 'Unit Grup - Inventaris UNDIRA')

@section('content')
@php
    $sample = $items->first();
    $groupBreadcrumbs = [
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Data Inventaris', 'url' => route('inventory.index')],
    ];
    if ($sample?->category) {
        $groupBreadcrumbs[] = ['label' => $sample->category->name, 'url' => route('inventory.category', $sample->category)];
    }
    $groupBreadcrumbs[] = ['label' => 'Unit Grup'];
    $groupBackRoute = $sample?->category
        ? route('inventory.category', $sample->category)
        : route('inventory.index');
    $groupBackLabel = $sample?->category?->name ?? 'Data Inventaris';
@endphp
<x-page-header
    :title="$sample?->name ?? 'Unit dalam Grup'"
    :subtitle="$items->count().' unit individual'.($sample?->category ? ' · '.$sample->category->name : '')"
    icon="bi bi-layers"
    :breadcrumbs="$groupBreadcrumbs"
    :back-route="$groupBackRoute"
    :back-label="$groupBackLabel"
/>

<div class="card card-undira d-none d-md-block">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>No. Inventaris</th>
                    <th>Serial</th>
                    <th>QR</th>
                    <th>Status</th>
                    <th>Kondisi</th>
                    <th>Ruangan</th>
                    <th>Gedung</th>
                    <th>Kampus</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td><code>{{ str_pad((string) ($item->unit_sequence ?? 1), 3, '0', STR_PAD_LEFT) }}</code></td>
                    <td><code class="text-break">{{ $item->inventory_number }}</code></td>
                    <td class="text-break">{{ $item->serial_number ?? '-' }}</td>
                    <td>
                        @if($item->qr_url)
                            <img src="{{ $item->qr_url }}" alt="QR" width="40" height="40" class="rounded border">
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td><span class="badge bg-secondary">{{ $item->status?->label() }}</span></td>
                    <td><span class="badge bg-info">{{ $item->condition?->label() }}</span></td>
                    <td>{{ $item->room?->name ?? '-' }}</td>
                    <td>{{ $item->room?->floor?->building?->name ?? '-' }}</td>
                    <td>{{ $item->room?->floor?->building?->campus?->name ?? '-' }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('inventory.show', $item) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                        @can('inventory.update')
                        <a href="{{ route('inventory.edit', $item) }}" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center text-muted py-4">Tidak ada unit dalam grup ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

{{-- Mobile cards --}}
<div class="d-md-none mt-3">
    @foreach($items as $item)
    <div class="card card-undira mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <strong>{{ $item->name }}</strong>
                    <div><code>#{{ str_pad((string) ($item->unit_sequence ?? 1), 3, '0', STR_PAD_LEFT) }}</code></div>
                </div>
                @if($item->qr_url)
                    <img src="{{ $item->qr_url }}" alt="QR" width="48" height="48" class="rounded border">
                @endif
            </div>
            <div class="small text-muted mb-2 text-break">
                <div>No: {{ $item->inventory_number }}</div>
                <div>SN: {{ $item->serial_number ?? '-' }}</div>
                <div>Serial: {{ $item->serial_number ?? '-' }}</div>
                <div>{{ $item->room?->floor?->building?->campus?->name }} / {{ $item->room?->floor?->building?->name }} / {{ $item->room?->name }}</div>
            </div>
            <div class="d-flex gap-1 flex-wrap">
                <span class="badge bg-secondary">{{ $item->status?->label() }}</span>
                <span class="badge bg-info">{{ $item->condition?->label() }}</span>
                <a href="{{ route('inventory.show', $item) }}" class="btn btn-sm btn-outline-primary ms-auto">Detail</a>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
