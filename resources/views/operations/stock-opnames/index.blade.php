@extends('layouts.app')

@section('title', 'Stock Opname - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Operasi</li>
    <li class="breadcrumb-item active">Stock Opname</li>
@endsection

@section('content')
<x-ui.page-header title="Stock Opname" subtitle="Pencatatan dan verifikasi stok inventaris" icon="bi bi-clipboard-check">
    @can('stock-opname.create')
    <a href="{{ route('stock-opnames.create') }}" class="btn btn-undira btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah</a>
    @endcan
</x-ui.page-header>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Judul</th><th>Kampus</th><th>Status</th><th>Item</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($stockOpnames as $opname)
                <tr>
                    <td>{{ $opname->title }}</td>
                    <td>{{ $opname->campus?->name }}</td>
                    <td><span class="badge bg-secondary">{{ $opname->status?->label() ?? $opname->status }}</span></td>
                    <td>{{ $opname->items_count }}</td>
                    <td>{{ $opname->created_at->format('d/m/Y') }}<br><small>{{ $opname->creator?->name }}</small></td>
                    <td><a href="{{ route('stock-opnames.show', $opname) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada stock opname.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($stockOpnames->hasPages())<div class="card-footer">{{ $stockOpnames->links() }}</div>@endif
</div>
@endsection
