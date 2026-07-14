@extends('layouts.app')

@section('title', 'Detail Maintenance - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('maintenances.index') }}">Maintenance</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-wrench me-2"></i>Detail Maintenance</h1>
    @can('maintenance.update')
    <a href="{{ route('maintenances.edit', $maintenance) }}" class="btn btn-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
    @endcan
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-undira">
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Vendor</th><td>{{ $maintenance->vendor?->name ?? '-' }}</td></tr>
                    <tr><th>Tgl Servis</th><td>{{ $maintenance->service_date?->format('d/m/Y') }}</td></tr>
                    <tr><th>Jadwal Berikut</th><td>{{ $maintenance->next_schedule?->format('d/m/Y') ?? '-' }}</td></tr>
                    <tr><th>Biaya</th><td>{{ $maintenance->cost ? 'Rp ' . number_format($maintenance->cost, 0, ',', '.') : '-' }}</td></tr>
                    <tr><th>Status</th><td><span class="badge bg-info">{{ $maintenance->status?->label() }}</span></td></tr>
                    <tr><th>Deskripsi</th><td>{{ $maintenance->description ?? '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-undira">
            <div class="card-header"><h5 class="mb-0">Inventaris</h5></div>
            <div class="card-body">
                @if($maintenance->inventoryItem)
                <p><strong>{{ $maintenance->inventoryItem->name }}</strong></p>
                <p><code>{{ $maintenance->inventoryItem->inventory_number }}</code></p>
                <a href="{{ route('inventory.show', $maintenance->inventoryItem) }}" class="btn btn-outline-primary btn-sm">Lihat Inventaris</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
