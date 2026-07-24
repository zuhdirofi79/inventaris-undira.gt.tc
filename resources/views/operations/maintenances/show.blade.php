@extends('layouts.app')

@section('title', ($maintenance->maintenance_number ?? 'Maintenance').' - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="{{ $maintenance->maintenance_number }}"
    subtitle="{{ $maintenance->inventoryItem?->inventory_number }} · {{ $maintenance->inventoryItem?->name }}"
    icon="bi bi-wrench-adjustable"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pemeliharaan Aset', 'url' => route('maintenances.index')],
        ['label' => $maintenance->maintenance_number],
    ]"
    :back-route="route('maintenances.index')"
    back-label="Pemeliharaan Aset"
>
    @can('maintenance.update')
        @if($maintenance->status?->isOpen())
        <a href="{{ route('maintenances.complete', $maintenance) }}" class="btn btn-undira btn-sm"><i class="bi bi-check2-circle me-1"></i>Selesaikan Perbaikan</a>
        @endif
        <a href="{{ route('maintenances.edit', $maintenance) }}" class="btn btn-outline-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
    @endcan
    @can('maintenance.approve')
        @if($maintenance->status?->value === 'pending_approval')
        <form action="{{ route('maintenance-approvals.approve', $maintenance) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-success btn-sm" onclick="return confirm('Approve perbaikan ini?')">Approve</button>
        </form>
        @endif
    @endcan
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-undira mb-3">
            <div class="card-header"><h5 class="mb-0">Detail Work Order</h5></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge bg-info text-dark">{{ $maintenance->status?->label() }}</span></dd>
                    <dt class="col-sm-4">Jenis</dt><dd class="col-sm-8">{{ $maintenance->maintenance_type?->label() ?? '—' }}</dd>
                    <dt class="col-sm-4">Prioritas</dt><dd class="col-sm-8">{{ $maintenance->priority?->label() ?? '—' }}</dd>
                    <dt class="col-sm-4">Tanggal</dt><dd class="col-sm-8">{{ $maintenance->service_date?->format('d/m/Y') }}</dd>
                    <dt class="col-sm-4">Teknisi</dt><dd class="col-sm-8">{{ $maintenance->technicianUser?->name ?? ($maintenance->technician ?: '—') }}</dd>
                    <dt class="col-sm-4">Jenis Kerusakan</dt><dd class="col-sm-8">{{ $maintenance->damage_type ?: '—' }}</dd>
                    <dt class="col-sm-4">Analisa</dt><dd class="col-sm-8">{{ $maintenance->analysis ?: '—' }}</dd>
                    <dt class="col-sm-4">Pekerjaan</dt><dd class="col-sm-8">{{ $maintenance->work_performed ?: '—' }}</dd>
                    <dt class="col-sm-4">Vendor</dt><dd class="col-sm-8">{{ $maintenance->vendor?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">Estimasi Selesai</dt><dd class="col-sm-8">{{ $maintenance->estimated_completion?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="col-sm-4">Durasi</dt><dd class="col-sm-8">{{ $maintenance->durationHours() !== null ? $maintenance->durationHours().' jam' : '—' }}</dd>
                    <dt class="col-sm-4">Estimasi / Aktual</dt>
                    <dd class="col-sm-8">
                        {{ $maintenance->estimated_cost ? 'Rp '.number_format($maintenance->estimated_cost, 0, ',', '.') : '—' }}
                        /
                        {{ $maintenance->cost ? 'Rp '.number_format($maintenance->cost, 0, ',', '.') : '—' }}
                    </dd>
                    <dt class="col-sm-4">Hasil</dt><dd class="col-sm-8">{{ $maintenance->result?->label() ?? '—' }}</dd>
                    <dt class="col-sm-4">Selesai</dt><dd class="col-sm-8">{{ $maintenance->completed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                    <dt class="col-sm-4">Approval</dt>
                    <dd class="col-sm-8">
                        @if($maintenance->approved_at)
                            Disetujui {{ $maintenance->approver?->name }} · {{ $maintenance->approved_at->format('d/m/Y H:i') }}
                        @elseif($maintenance->rejection_reason)
                            Revisi: {{ $maintenance->rejection_reason }}
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4">Deskripsi</dt><dd class="col-sm-8">{{ $maintenance->description ?: '—' }}</dd>
                    <dt class="col-sm-4">Sparepart</dt><dd class="col-sm-8">{{ $maintenance->parts_replaced ?: '—' }}</dd>
                    <dt class="col-sm-4">Catatan</dt><dd class="col-sm-8">{{ $maintenance->notes ?: '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header"><h5 class="mb-0">Foto</h5></div>
            <div class="card-body">
                @php
                    $groups = ['before' => 'Sebelum', 'during' => 'Saat', 'after' => 'Sesudah'];
                @endphp
                @foreach($groups as $key => $label)
                    <h6 class="text-muted">{{ $label }}</h6>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @forelse($maintenance->photos->where('type', $key) as $photo)
                            <a href="{{ $photo->url }}" target="_blank" rel="noopener">
                                <img src="{{ $photo->url }}" alt="{{ $label }}" class="rounded border" style="height:96px;width:96px;object-fit:cover;">
                            </a>
                        @empty
                            <span class="text-muted small">Tidak ada</span>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card card-undira">
            <div class="card-header"><h5 class="mb-0">Riwayat Status</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Waktu</th><th>Dari</th><th>Ke</th><th>Oleh</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @forelse($maintenance->statusHistories as $hist)
                        <tr>
                            <td>{{ $hist->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td>{{ $hist->from_status?->label() ?? '—' }}</td>
                            <td>{{ $hist->to_status?->label() }}</td>
                            <td>{{ $hist->changedBy?->name ?? '—' }}</td>
                            <td>{{ $hist->note ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-undira">
            <div class="card-header"><h5 class="mb-0">Inventaris</h5></div>
            <div class="card-body small">
                <div class="mb-2"><code>{{ $assetCard['inventory_number'] }}</code></div>
                <div class="fw-semibold mb-2">{{ $assetCard['name'] }}</div>
                <div>{{ $assetCard['category'] }} / {{ $assetCard['sub_category'] ?: '—' }}</div>
                <div class="text-muted">{{ collect([$assetCard['campus'], $assetCard['building'], $assetCard['floor'], $assetCard['room']])->filter()->implode(' · ') }}</div>
                <div class="mt-2">PIC: {{ $assetCard['pic'] ?: '—' }}</div>
                <div>Status aset: <strong>{{ $assetCard['status_label'] }}</strong></div>
                @can('inventory.view')
                <a href="{{ route('inventory.show', $maintenance->inventory_item_id) }}" class="btn btn-outline-primary btn-sm mt-3 w-100">Buka Detail Inventaris</a>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection
