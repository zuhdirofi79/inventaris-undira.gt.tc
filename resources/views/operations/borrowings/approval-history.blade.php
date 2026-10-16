@extends('layouts.app')

@section('title', 'Riwayat Approval - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Riwayat Approval"
    subtitle="Riwayat persetujuan dan penolakan peminjaman"
    icon="bi bi-clipboard-check"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Riwayat Approval'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
>
    <a href="{{ route('borrowing-approvals.export.pdf', request()->query()) }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-file-pdf me-1"></i>Export PDF</a>
    <a href="{{ route('borrowing-approvals.export.excel', request()->query()) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel me-1"></i>Export Excel</a>
</x-page-header>

<div class="card card-undira mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('borrowing-approvals.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Cari</label>
                <input type="text" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="No approval / nama / barang">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="approval-campus">Lokasi Kampus</label>
                <select id="approval-campus" name="campus_id" class="form-select">
                    <option value="">Semua Kampus</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}" @selected((string) ($filters['campus_id'] ?? '') === (string) $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
            </div>
            <div class="col-12 col-md-6 col-xl-auto d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-undira"><i class="fas fa-search me-1"></i>Filter</button>
                <a href="{{ route('borrowing-approvals.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
        @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0 py-2">{{ $errors->first() }}</div>
        @endif
    </div>
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Nomor Approval</th>
                    <th>Tanggal</th>
                    <th>Nama User</th>
                    <th>Barang</th>
                    <th>Status</th>
                    <th>Approved By</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($borrowings as $borrowing)
                <tr>
                    <td>
                        @if($borrowing->approval_number)
                            <a href="{{ route('borrowing.approval.public', $borrowing->approval_number) }}">{{ $borrowing->approval_number }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ ($borrowing->approved_at ?? $borrowing->rejected_at)?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $borrowing->borrower_name }}</td>
                    <td>
                        {{ $borrowing->inventoryItem?->name }}
                        <br><small class="text-muted">{{ $borrowing->inventoryItem?->inventory_number }}</small>
                    </td>
                    <td><span class="badge bg-{{ $borrowing->status?->badgeColor() ?? 'secondary' }}">{{ $borrowing->status?->label() }}</span></td>
                    <td>{{ $borrowing->approvedBy?->name ?? '—' }}</td>
                    <td>
                        @include('partials.approval-history-actions', [
                            'detailUrl' => route('borrowings.show', $borrowing),
                            'documentUrl' => $borrowing->hasSupportingDocument() ? route('borrowings.supporting-document.view', $borrowing) : null,
                            'receiptPdfUrl' => $borrowing->approval_number ? route('borrowings.receipt.pdf', $borrowing) : null,
                        ])
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat approval.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($borrowings->hasPages())<div class="card-footer">{{ $borrowings->links() }}</div>@endif
</div>
@endsection
