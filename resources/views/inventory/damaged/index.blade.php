@extends('layouts.app')

@section('title', 'Barang Rusak - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Daftar Barang Rusak"
    subtitle="Rusak ringan/berat, tidak dapat diperbaiki, dan perlu penggantian. Hapus hanya Primary Super Admin."
    icon="bi bi-exclamation-triangle"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Barang Rusak'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
/>

<form method="GET" class="card card-undira mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nomor / nama / PIC">
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">Semua rusak</option>
                @foreach($filterStatuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-undira w-100">Filter</button>
        </div>
    </div>
</form>

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>No. Inventaris</th>
                        <th>Nama</th>
                        <th>Lokasi</th>
                        <th>PIC</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td><code>{{ $item->inventory_number }}</code></td>
                        <td>{{ $item->name }}</td>
                        <td class="small">{{ $item->room?->floor?->building?->campus?->name }} · {{ $item->room?->name }}</td>
                        <td>{{ $item->picDisplayName() ?: '—' }}</td>
                        <td><span class="badge text-bg-danger">{{ $item->status?->label() }}</span></td>
                        <td class="text-nowrap">
                            <a href="{{ route('inventory.show', $item) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            @if($canDelete)
                            <form action="{{ route('inventory.damaged.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Pindahkan ke sampah? Hanya Primary Super Admin.');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus (Primary SA)"><i class="fas fa-trash"></i></button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada barang rusak.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($items->hasPages())<div class="card-footer">{{ $items->links() }}</div>@endif
</div>
@endsection
