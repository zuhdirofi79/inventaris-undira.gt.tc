@extends('layouts.app')

@section('title', 'Peminjaman - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Peminjaman"
    subtitle="Kelola peminjaman inventaris"
    icon="bi bi-hand-index"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Peminjaman'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
>
    @can('borrowing.create')
    <a href="{{ route('borrowings.create') }}" class="btn btn-undira btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah</a>
    @endcan
</x-page-header>

<form method="GET" action="{{ route('borrowings.index') }}" class="card card-undira mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-sm-6 col-lg-4">
            <label class="form-label mb-1" for="filter-campus">Lokasi Kampus</label>
            <select id="filter-campus" name="campus_id" class="form-select form-select-sm">
                <option value="">Semua Kampus</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) ($campusFilter ?? '') === (string) $campus->id)>
                        {{ $campus->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label mb-1" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($statusOptions as $opt)
                    <option value="{{ $opt->value }}" @selected(($statusFilter ?? '') === $opt->value)>{{ $opt->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-lg-3">
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-sm btn-undira">Filter</button>
                @if(!empty($campusFilter) || filled($statusFilter ?? null))
                    <a href="{{ route('borrowings.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>No. Approval</th>
                        <th>Inventaris</th>
                        <th>Kampus</th>
                        <th>Jml Item</th>
                        <th>Peminjam</th>
                        <th>Tgl Pinjam</th>
                        <th>Tgl Kembali</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($borrowings as $borrowing)
                    @php
                        $count = $borrowing->items_count ?? $borrowing->items->count();
                        $first = $borrowing->items->first()?->inventoryItem ?? $borrowing->inventoryItem;
                        $campusLabel = $borrowing->campusSummary();
                        $isMultiCampus = $borrowing->isMultiCampus();
                    @endphp
                    <tr>
                        <td>
                            @if($borrowing->approval_number)
                                <code>{{ $borrowing->approval_number }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($first)
                                <a href="{{ route('inventory.show', $first) }}">{{ $first->inventory_number }}</a>
                                <br><small class="text-muted">{{ $first->name }}</small>
                                @if($count > 1)
                                    <br><small class="text-muted">+{{ $count - 1 }} barang lain</small>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($isMultiCampus)
                                <span class="badge bg-warning text-dark" title="{{ $borrowing->campusNames()->implode(', ') }}">Multi Kampus</span>
                            @else
                                <span class="small">{{ $campusLabel }}</span>
                            @endif
                        </td>
                        <td>{{ $count ?: ($borrowing->inventoryItem ? 1 : 0) }}</td>
                        <td>{{ $borrowing->borrower_name }}<br><small class="text-muted">{{ $borrowing->borrower_email }}</small></td>
                        <td>{{ $borrowing->borrow_date?->format('d/m/Y') }}</td>
                        <td>{{ $borrowing->expected_return_date?->format('d/m/Y') ?? '-' }}</td>
                        <td>
                            <span class="badge bg-{{ $borrowing->status?->badgeColor() ?? 'secondary' }}">{{ $borrowing->status?->label() }}</span>
                        </td>
                        <td>
                            <a href="{{ route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            @can('borrowing.update')
                            <a href="{{ route('borrowings.edit', $borrowing) }}" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                            @endcan
                            @can('approve', $borrowing)
                            <a href="{{ route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-primary" title="Approval per item">
                                <i class="fas fa-tasks"></i> Approval
                            </a>
                            <form action="{{ route('borrowings.approve', $borrowing) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success" title="Setujui semua item menunggu"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectBorrowingModal{{ $borrowing->id }}" title="Tolak semua item menunggu">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            @endcan
                        </td>
                    </tr>
                    @can('approve', $borrowing)
                    @if($borrowing->status?->isPending())
                    <div class="modal fade" id="rejectBorrowingModal{{ $borrowing->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <form action="{{ route('borrowings.reject', $borrowing) }}" method="POST" class="modal-content">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title">Tolak Peminjaman</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-danger">Tolak</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif
                    @endcan
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            @if(!empty($campusFilter))
                                Tidak ada peminjaman pada kampus yang dipilih.
                            @elseif(filled($statusFilter ?? null))
                                Tidak ada peminjaman dengan status yang dipilih.
                            @else
                                Belum ada data peminjaman.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($borrowings->hasPages())<div class="card-footer">{{ $borrowings->links() }}</div>@endif
</div>
@endsection
