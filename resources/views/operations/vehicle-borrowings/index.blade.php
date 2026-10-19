@extends('layouts.app')

@php
    use App\Enums\FacilityRequestStatus;
    use App\Support\ApprovalCancellationWindow;

    $isApproval = $mode === 'approvals';
    $title = $isApproval ? 'Approval Kendaraan' : 'Riwayat Approval Kendaraan';
    $tz = (string) config('app.timezone');
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
            'showDates' => ! $isApproval,
            'dateFrom' => $dateFrom ?? '',
            'dateTo' => $dateTo ?? '',
            'statuses' => $isApproval ? [] : [
                ['value' => 'disetujui', 'label' => 'Disetujui'],
                ['value' => 'ditolak', 'label' => 'Ditolak'],
                ['value' => 'persetujuan_dibatalkan', 'label' => 'Persetujuan Dibatalkan'],
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
                        @unless($isApproval)
                            <th>Tanggal &amp; Waktu Pengajuan</th>
                            <th>Tanggal &amp; Waktu Keputusan</th>
                        @endunless
                        <th>Status</th>
                        <th>Diproses</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($borrowings as $borrowing)
                        @php
                            $decisionAt = $borrowing->decisionTimestamp();
                            $canCancel = ! $isApproval
                                && $borrowing->status === FacilityRequestStatus::Disetujui
                                && $borrowing->canCancelApproval();
                            $showExpired = ! $isApproval
                                && $borrowing->status === FacilityRequestStatus::Disetujui
                                && ! $canCancel
                                && $borrowing->approved_at;
                        @endphp
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
                            @unless($isApproval)
                                <td>{{ $borrowing->created_at?->timezone($tz)->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>{{ $decisionAt ? $decisionAt->timezone($tz)->format('d/m/Y H:i') : '—' }}</td>
                            @endunless
                            <td>
                                <span class="badge bg-{{ $borrowing->displayColor() }}">{{ $borrowing->displayLabel() }}</span>
                                @if($borrowing->cancellation_reason)
                                    <div class="small text-muted mt-1">{{ $borrowing->cancellation_reason }}</div>
                                @elseif($borrowing->rejection_reason)
                                    <div class="small text-muted mt-1">{{ $borrowing->rejection_reason }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $borrowing->cancelledBy?->name ?: ($borrowing->approvedBy?->name ?: '—') }}
                                <div class="small text-muted">{{ $decisionAt ? $decisionAt->timezone($tz)->format('d/m/Y H:i') : '—' }}</div>
                            </td>
                            <td class="text-end">
                                @include('partials.approval-history-actions', [
                                    'detailUrl' => route('vehicle-borrowings.show', $borrowing),
                                    'documentUrl' => $borrowing->supporting_document_path ? route('vehicle-borrowings.document.preview', $borrowing) : null,
                                    'receiptPdfUrl' => $borrowing->approval_number ? route('vehicle-borrowings.receipt.pdf', $borrowing) : null,
                                    'canCancel' => $canCancel,
                                    'cancelUrl' => route('vehicle-borrowings.cancel-approval', $borrowing),
                                    'kindLabel' => 'Peminjaman Kendaraan Operasional',
                                    'number' => $borrowing->approval_number ?: '#'.$borrowing->id,
                                    'borrower' => $borrowing->borrower_name,
                                    'approvedAt' => $borrowing->approved_at,
                                    'modalId' => 'cancelVehicle'.$borrowing->id,
                                    'showExpiredHint' => $showExpired,
                                    'expiredHint' => ApprovalCancellationWindow::remainingLabel($borrowing->approved_at),
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isApproval ? 8 : 10 }}" class="text-center text-muted py-4">Tidak ada pengajuan kendaraan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $borrowings->links() }}</div>
    </div>
</div>
@endsection
