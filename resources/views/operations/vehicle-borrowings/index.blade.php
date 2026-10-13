@extends('layouts.app')

@php
    $isApproval = $mode === 'approvals';
    $title = $isApproval ? 'Approval Kendaraan' : 'Riwayat Approval Kendaraan';
@endphp

@section('title', $title.' - Inventaris UNDIRA')

@section('content')
<x-page-header
    :title="$title"
    :subtitle="$isApproval ? 'Pengajuan kendaraan operasional yang masih menunggu keputusan.' : 'Hasil persetujuan dan penolakan peminjaman kendaraan.'"
    icon="bi bi-truck"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => $title],
    ]"
/>

<div class="card card-undira">
    <div class="card-body">
        @include('operations.facility._filters', [
            'action' => $isApproval ? route('vehicle-borrowings.approvals') : route('vehicle-borrowings.history'),
            'campuses' => $campuses,
            'campusId' => $campusId,
            'search' => $search,
            'status' => $status,
            'statuses' => $isApproval ? [] : [
                ['value' => 'disetujui', 'label' => 'Disetujui'],
                ['value' => 'ditolak', 'label' => 'Ditolak'],
                ['value' => 'selesai', 'label' => 'Selesai'],
            ],
        ])

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Peminjam</th>
                        <th>Layanan</th>
                        <th>Kampus</th>
                        <th>Jadwal</th>
                        <th>Status</th>
                        <th>Diproses</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($borrowings as $borrowing)
                        <tr>
                            <td>
                                @if($borrowing->approval_number)
                                    <a href="{{ route('vehicle-borrowings.approval', $borrowing) }}">{{ $borrowing->approval_number }}</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $borrowing->borrower_name }}</td>
                            <td>
                                {{ $borrowing->service_type?->label() ?: '—' }}
                                @if($borrowing->vehicle)
                                    <div class="small text-muted">{{ $borrowing->vehicle->plate_number }}</div>
                                @elseif($borrowing->assigned_service)
                                    <div class="small text-muted">{{ $borrowing->assigned_service->label() }}</div>
                                @endif
                            </td>
                            <td>{{ $borrowing->campus?->name ?: '—' }}</td>
                            <td>{{ $borrowing->scheduleText() }}</td>
                            <td>
                                <span class="badge bg-{{ $borrowing->displayColor() }}">{{ $borrowing->displayLabel() }}</span>
                                @if($borrowing->rejection_reason)
                                    <div class="small text-muted mt-1">{{ $borrowing->rejection_reason }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $borrowing->approvedBy?->name ?: '—' }}
                                @php $decidedAt = $borrowing->approved_at ?? $borrowing->rejected_at; @endphp
                                <div class="small text-muted">{{ $decidedAt ? $decidedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}</div>
                            </td>
                            <td class="text-end">
                                @include('partials.approval-history-actions', [
                                    'detailUrl' => route('vehicle-borrowings.show', $borrowing),
                                    'documentUrl' => $borrowing->supporting_document_path ? route('vehicle-borrowings.document.preview', $borrowing) : null,
                                    'receiptPdfUrl' => $borrowing->approval_number ? route('vehicle-borrowings.receipt.pdf', $borrowing) : null,
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pengajuan kendaraan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $borrowings->links() }}</div>
    </div>
</div>
@endsection
