@extends('layouts.app')

@section('title', 'Hasil Laporan - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Laporan</a></li>
    <li class="breadcrumb-item active">Hasil</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1><i class="fas fa-chart-bar me-2"></i>Hasil Laporan</h1>
        <p class="text-muted mb-0">{{ now()->translatedFormat('d F Y H:i') }}</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i>Cetak</button>
        <a href="{{ route('reports.index') }}" class="btn btn-undira btn-sm"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card card-undira"><div class="card-body"><div class="text-muted small">Total Item</div><div class="fs-4 fw-bold">{{ number_format($summary['total_items'] ?? 0) }}</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card card-undira"><div class="card-body"><div class="text-muted small">Total Nilai</div><div class="fs-4 fw-bold">Rp {{ number_format($summary['total_value'] ?? 0, 0, ',', '.') }}</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card card-undira"><div class="card-body"><div class="text-muted small">Status</div><div class="small">@foreach(($summary['by_status'] ?? []) as $status => $count)<span class="badge bg-secondary me-1">{{ $status ?: '-' }}: {{ $count }}</span>@endforeach</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card card-undira"><div class="card-body"><div class="text-muted small">Kategori</div><div class="small">@foreach(($summary['by_category'] ?? []) as $cat => $count)<span class="badge bg-info me-1">{{ $cat ?: '-' }}: {{ $count }}</span>@endforeach</div></div></div>
    </div>
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>No. Inventaris</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>Harga</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td><a href="{{ route('inventory.show', $item) }}"><code>{{ $item->inventory_number }}</code></a></td>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->category?->name ?? '-' }}</td>
                        <td><small>{{ $item->full_location }}</small></td>
                        <td><span class="badge bg-secondary">{{ $item->status?->label() ?? '-' }}</span></td>
                        <td><span class="badge bg-info">{{ $item->condition?->label() ?? '-' }}</span></td>
                        <td>{{ $item->price ? 'Rp '.number_format($item->price, 0, ',', '.') : '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
