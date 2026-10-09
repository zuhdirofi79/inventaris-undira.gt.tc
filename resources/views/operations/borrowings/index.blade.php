@extends('layouts.app')

@section('title', 'Peminjaman - Inventaris UNDIRA')

@push('styles')
<style>
.borrowing-list-table th,
.borrowing-list-table td {
  vertical-align: middle;
  font-size: 0.875rem;
  line-height: 1.35;
}
.borrowing-list-table .borrowing-inv__number {
  display: inline-block;
  white-space: nowrap;
  font-weight: 600;
}
.borrowing-list-table .col-aksi {
  width: 1%;
  white-space: nowrap;
}
.borrowing-row-actions {
  display: inline-flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 0.35rem;
}
.borrowing-row-actions .btn {
  white-space: nowrap;
}
.borrowing-list-table .badge {
  white-space: nowrap;
}
</style>
@endpush

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
                @if(!empty($campusFilter) || !empty($campusFilterActive) || filled($statusFilter ?? null))
                    <a href="{{ route('borrowings.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 borrowing-list-table">
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
                        <td class="borrowing-inv">
                            @if($first)
                                <a class="borrowing-inv__number" href="{{ route('inventory.show', $first) }}">{{ $first->inventory_number }}</a>
                                <div class="small text-muted">{{ $first->name }}</div>
                                @if($count > 1)
                                    <div class="small text-muted">+{{ $count - 1 }} barang lain</div>
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
                        <td>{{ $borrowing->plannedScheduleDate('borrow_date') }}@if($borrowing->plannedScheduleTime('borrow_date'))<br><span class="small text-muted">{{ $borrowing->plannedScheduleTime('borrow_date') }}</span>@endif</td>
                        <td>{{ $borrowing->plannedScheduleDate('expected_return_date') }}@if($borrowing->plannedScheduleTime('expected_return_date'))<br><span class="small text-muted">{{ $borrowing->plannedScheduleTime('expected_return_date') }}</span>@endif</td>
                        <td>
                            <span class="badge bg-{{ $borrowing->status?->badgeColor() ?? 'secondary' }}">{{ $borrowing->status?->label() }}</span>
                        </td>
                        <td class="col-aksi">
                            <div class="borrowing-row-actions">
                                <a href="{{ route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-primary" title="Lihat" aria-label="Lihat"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                @can('borrowing.update')
                                <a href="{{ route('borrowings.edit', $borrowing) }}" class="btn btn-sm btn-outline-warning" title="Edit" aria-label="Edit"><i class="fas fa-edit" aria-hidden="true"></i></a>
                                @endcan
                                @can('approve', $borrowing)
                                <a href="{{ route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-undira">Approval</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            @if(!empty($campusFilter) || !empty($campusFilterActive))
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
