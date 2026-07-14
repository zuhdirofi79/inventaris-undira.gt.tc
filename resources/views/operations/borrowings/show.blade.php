@extends('layouts.app')

@section('title', 'Detail Peminjaman - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('borrowings.index') }}">Peminjaman</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-hand-holding me-2"></i>Detail Peminjaman</h1>
    @can('borrowing.update')
    <a href="{{ route('borrowings.edit', $borrowing) }}" class="btn btn-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
    @endcan
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Data Peminjaman</h5></div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Peminjam</th><td>{{ $borrowing->borrower_name }}</td></tr>
                    <tr><th>Email</th><td>{{ $borrowing->borrower_email ?? '-' }}</td></tr>
                    <tr><th>Telepon</th><td>{{ $borrowing->borrower_phone ?? '-' }}</td></tr>
                    <tr><th>Tgl Pinjam</th><td>{{ $borrowing->borrow_date?->format('d/m/Y') }}</td></tr>
                    <tr><th>Tgl Kembali (Rencana)</th><td>{{ $borrowing->expected_return_date?->format('d/m/Y') ?? '-' }}</td></tr>
                    <tr><th>Tgl Kembali (Aktual)</th><td>{{ $borrowing->return_date?->format('d/m/Y') ?? '-' }}</td></tr>
                    <tr><th>Status</th><td><span class="badge bg-warning">{{ $borrowing->status?->label() }}</span></td></tr>
                    <tr><th>Disetujui Oleh</th><td>{{ $borrowing->approvedBy?->name ?? '-' }}</td></tr>
                    <tr><th>Catatan</th><td>{{ $borrowing->notes ?? '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Inventaris</h5></div>
            <div class="card-body">
                @if($borrowing->inventoryItem)
                <p><strong>{{ $borrowing->inventoryItem->name }}</strong></p>
                <p><code>{{ $borrowing->inventoryItem->inventory_number }}</code></p>
                <p class="text-muted small">{{ $borrowing->inventoryItem->full_location }}</p>
                <a href="{{ route('inventory.show', $borrowing->inventoryItem) }}" class="btn btn-outline-primary btn-sm">Lihat Inventaris</a>
                @else
                <p class="text-muted">Inventaris tidak ditemukan.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
