@extends('layouts.app')

@php
    $isApproval = $mode === 'approvals';
    $title = $isApproval ? 'Approval Ruangan' : 'Riwayat Approval Ruangan';
@endphp

@section('title', $title.' - Inventaris UNDIRA')

@section('content')
<x-page-header
    :title="$title"
    :subtitle="$isApproval ? 'Pengajuan ruangan yang masih menunggu keputusan.' : 'Hasil persetujuan dan penolakan peminjaman ruangan.'"
    icon="bi bi-door-open"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => $title],
    ]"
/>

<div class="card card-undira">
    <div class="card-body">
        @include('operations.facility._filters', [
            'action' => $isApproval ? route('room-borrowings.approvals') : route('room-borrowings.history'),
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
                        <th>Ruangan</th>
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
                                    <a href="{{ route('room-borrowings.approval', $borrowing) }}">{{ $borrowing->approval_number }}</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                {{ $borrowing->borrower_name }}
                                @if($borrowing->activity_type)
                                    <div class="small text-muted">{{ $borrowing->activity_type->label() }}</div>
                                @endif
                            </td>
                            <td>{{ $borrowing->room?->name ?: '—' }}</td>
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
                                @php
                                    $documentUrl = null;
                                    foreach (['approval', 'layout', 'participants'] as $documentKind) {
                                        if ($borrowing->documentMeta($documentKind)) {
                                            $documentUrl = route('room-borrowings.documents.preview', [$borrowing, $documentKind]);
                                            break;
                                        }
                                    }
                                @endphp
                                @include('partials.approval-history-actions', [
                                    'detailUrl' => route('room-borrowings.show', $borrowing),
                                    'documentUrl' => $documentUrl,
                                    'receiptPdfUrl' => $borrowing->approval_number ? route('room-borrowings.receipt.pdf', $borrowing) : null,
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pengajuan ruangan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $borrowings->links() }}</div>
    </div>
</div>
@endsection
