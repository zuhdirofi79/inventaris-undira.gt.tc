@extends('layouts.app')

@section('title', 'Peminjaman - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Operasi</li>
    <li class="breadcrumb-item active">Peminjaman</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-hand-holding me-2"></i>Peminjaman</h1>
    @can('borrowing.create')
    <a href="{{ route('borrowings.create') }}" class="btn btn-undira btn-sm"><i class="fas fa-plus me-1"></i>Tambah</a>
    @endcan
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Inventaris</th>
                    <th>Peminjam</th>
                    <th>Tgl Pinjam</th>
                    <th>Tgl Kembali</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($borrowings as $borrowing)
                <tr>
                    <td>
                        <a href="{{ route('inventory.show', $borrowing->inventoryItem) }}">{{ $borrowing->inventoryItem?->inventory_number }}</a>
                        <br><small class="text-muted">{{ $borrowing->inventoryItem?->name }}</small>
                    </td>
                    <td>{{ $borrowing->borrower_name }}<br><small>{{ $borrowing->borrower_email }}</small></td>
                    <td>{{ $borrowing->borrow_date?->format('d/m/Y') }}</td>
                    <td>{{ $borrowing->expected_return_date?->format('d/m/Y') ?? '-' }}</td>
                    <td><span class="badge bg-warning">{{ $borrowing->status?->label() }}</span></td>
                    <td>
                        <a href="{{ route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                        @can('borrowing.update')
                        <a href="{{ route('borrowings.edit', $borrowing) }}" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data peminjaman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($borrowings->hasPages())<div class="card-footer">{{ $borrowings->links() }}</div>@endif
</div>
@endsection
