@extends('layouts.app')

@section('title', 'Maintenance - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Operasi</li>
    <li class="breadcrumb-item active">Maintenance</li>
@endsection

@section('content')
<x-ui.page-header title="Maintenance" subtitle="Jadwal dan riwayat perawatan inventaris" icon="bi bi-wrench-adjustable">
    @can('maintenance.create')
    <a href="{{ route('maintenances.create') }}" class="btn btn-undira btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah</a>
    @endcan
</x-ui.page-header>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Inventaris</th><th>Vendor</th><th>Tgl Servis</th><th>Jadwal Berikut</th><th>Biaya</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($maintenances as $maintenance)
                <tr>
                    <td>
                        {{ $maintenance->inventoryItem?->inventory_number }}<br>
                        <small>{{ $maintenance->inventoryItem?->name }}</small>
                    </td>
                    <td>{{ $maintenance->vendor?->name ?? '-' }}</td>
                    <td>{{ $maintenance->service_date?->format('d/m/Y') }}</td>
                    <td>{{ $maintenance->next_schedule?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $maintenance->cost ? 'Rp ' . number_format($maintenance->cost, 0, ',', '.') : '-' }}</td>
                    <td><span class="badge bg-info">{{ $maintenance->status?->label() }}</span></td>
                    <td>
                        <a href="{{ route('maintenances.show', $maintenance) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                        @can('maintenance.update')
                        <a href="{{ route('maintenances.edit', $maintenance) }}" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data maintenance.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($maintenances->hasPages())<div class="card-footer">{{ $maintenances->links() }}</div>@endif
</div>
@endsection
