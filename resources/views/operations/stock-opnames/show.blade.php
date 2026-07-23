@extends('layouts.app')

@section('title', $stockOpname->title . ' - Stock Opname')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('stock-opnames.index') }}">Stock Opname</a></li>
    <li class="breadcrumb-item active">{{ $stockOpname->title }}</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1><i class="fas fa-clipboard-check me-2"></i>{{ $stockOpname->title }}</h1>
        <p class="text-muted mb-0">{{ $stockOpname->campus?->name }} &middot; Status: <span class="badge bg-secondary">{{ $stockOpname->status?->label() ?? $stockOpname->status }}</span></p>
    </div>
    <div class="d-flex gap-2 mt-2">
        @can('stock-opname.update')
        @if($stockOpname->canStart())
        <form action="{{ route('stock-opnames.start', $stockOpname) }}" method="POST">@csrf<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-play me-1"></i>Mulai</button></form>
        @endif
        @if($stockOpname->canComplete())
        <form action="{{ route('stock-opnames.complete', $stockOpname) }}" method="POST">@csrf<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Selesai</button></form>
        @endif
        @endcan
        @can('stock-opname.delete')
        @if($stockOpname->canDelete())
        <form action="{{ route('stock-opnames.destroy', $stockOpname) }}" method="POST" onsubmit="return confirm('Hapus stock opname ini?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Hapus</button>
        </form>
        @endif
        @endcan
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4"><div class="card card-undira card-stat"><div class="card-body"><div class="text-muted small">Total Discan</div><h3>{{ $stats['total_scanned'] }}</h3></div></div></div>
    <div class="col-md-4"><div class="card card-undira card-stat stat-blue"><div class="card-body"><div class="text-muted small">Total Item</div><h3>{{ $stats['total_items'] }}</h3></div></div></div>
    <div class="col-md-4"><div class="card card-undira card-stat"><div class="card-body"><div class="text-muted small">Dibuat Oleh</div><h5 class="mb-0">{{ $stockOpname->creator?->name }}</h5></div></div></div>
</div>

@can('stock-opname.update')
@if($stockOpname->canScan())
<div class="card card-undira mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Scan Inventaris</h5></div>
    <div class="card-body">
        <div class="input-group">
            <input type="text" id="scanInput" class="form-control" placeholder="Masukkan / scan nomor inventaris...">
            <button type="button" id="btnScan" class="btn btn-undira"><i class="fas fa-search"></i> Scan</button>
        </div>
        <div id="scanResult" class="mt-2"></div>
    </div>
</div>
@endif
@endcan

<div class="card card-undira">
    <div class="card-header"><h5 class="mb-0">Daftar Item</h5></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>No. Inventaris</th><th>Nama</th><th>Kategori</th><th>Discan</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>{{ $item->inventoryItem?->inventory_number }}</td>
                    <td>{{ $item->inventoryItem?->name }}</td>
                    <td>{{ $item->inventoryItem?->category?->name }}</td>
                    <td>{{ $item->scanned_at ? $item->scanned_at->format('d/m/Y H:i') : '-' }}</td>
                    <td><span class="badge bg-{{ $item->scanned_at ? 'success' : 'secondary' }}">{{ $item->status ?? ($item->scanned_at ? 'found' : 'pending') }}</span></td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada item. Mulai scan untuk menambahkan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())
    <div class="card-footer">{{ $items->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
$('#btnScan').on('click', function() {
    const num = $('#scanInput').val().trim();
    if (!num) return;
    $.post('{{ route('stock-opnames.scan', $stockOpname) }}', { inventory_number: num }, function(res) {
        showToast(res.message, 'success');
        $('#scanResult').html(`<div class="alert alert-success small mb-0">${res.item.inventory_number} - ${res.item.name}</div>`);
        setTimeout(() => location.reload(), 1000);
    }).fail(function(xhr) {
        showToast(xhr.responseJSON?.message || 'Gagal scan.', 'error');
    });
});
$('#scanInput').on('keypress', function(e) { if (e.which === 13) { e.preventDefault(); $('#btnScan').click(); } });
</script>
@endpush
