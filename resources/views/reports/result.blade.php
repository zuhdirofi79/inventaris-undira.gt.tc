@extends('layouts.app')

@section('title', 'Hasil Laporan - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Laporan</a></li>
    <li class="breadcrumb-item active">Hasil</li>
@endsection

@section('content')
<style>
    .report-h-campus { font-size: 1.35rem; color: #003366; border-bottom: 2px solid #003366; padding-bottom: .35rem; margin-top: 1.25rem; }
    .report-h-building { font-size: 1.15rem; color: #0d6efd; margin-top: 1rem; }
    .report-h-floor { font-size: 1.05rem; margin-top: .75rem; }
    .report-h-room { font-size: 1rem; margin-top: .5rem; background: #f8f9fa; padding: .4rem .6rem; border-left: 3px solid #198754; }
    .report-h-category { font-weight: 700; margin-top: .5rem; }
    .report-h-sub { font-weight: 600; margin-left: .5rem; color: #555; }
    .report-items { margin: .25rem 0 .5rem 1.25rem; }
    .report-items li { margin-bottom: .15rem; }
    .report-meta { color: #666; font-size: .9em; }
    .report-subtotal { font-size: .9rem; color: #444; border-top: 1px dashed #ccc; padding-top: .35rem; margin: .5rem 0 .75rem; }
    .report-campus-summary { background: #eef5ff; border: 1px solid #c5d8f0; padding: .75rem 1rem; margin: 1rem 0 1.5rem; border-radius: .35rem; }
    @media print {
        .no-print { display: none !important; }
        .report-h-room { background: #eee !important; }
    }
</style>

<div class="page-header-undira d-flex justify-content-between align-items-start flex-wrap gap-2 no-print">
    <div>
        <h1><i class="fas fa-chart-bar me-2"></i>{{ $meta['title'] }}</h1>
        <p class="text-muted mb-0">{{ $meta['printed_at'] }} · {{ $meta['user_name'] }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i>Cetak</button>
        <a href="{{ route('reports.index') }}" class="btn btn-undira btn-sm"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

@if(!empty($filters))
<div class="alert alert-light border mb-3 no-print">
    <strong>Filter aktif:</strong>
    @foreach($filters as $label => $value)
        <span class="badge bg-secondary me-1">{{ $label }}: {{ $value }}</span>
    @endforeach
</div>
@endif

<div class="row g-3 mb-3 no-print">
    <div class="col-md-3">
        <div class="card card-undira"><div class="card-body"><div class="text-muted small">Total Inventaris</div><div class="fs-4 fw-bold">{{ number_format($summary['total_items'] ?? 0) }}</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card card-undira"><div class="card-body"><div class="text-muted small">Total Nilai</div><div class="fs-4 fw-bold">Rp {{ number_format($summary['total_value'] ?? 0, 0, ',', '.') }}</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card card-undira"><div class="card-body"><div class="text-muted small">Status</div><div class="small">
            Aktif: {{ $summary['aktif'] ?? 0 }} · Dipinjam: {{ $summary['dipinjam'] ?? 0 }} · Maint: {{ $summary['maintenance'] ?? 0 }} · Rusak: {{ $summary['rusak'] ?? 0 }}
        </div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card card-undira"><div class="card-body"><div class="text-muted small">Kategori</div><div class="small">
            @foreach(($summary['by_category'] ?? []) as $cat => $count)
                <span class="badge bg-info me-1">{{ $cat }}: {{ $count }}</span>
            @endforeach
        </div></div></div>
    </div>
</div>

<div class="card card-undira">
    <div class="card-body">
        @include('reports._hierarchy', ['tree' => $tree, 'isPdf' => false])
    </div>
    <div class="card-footer">
        <strong>Grand Total:</strong>
        {{ number_format($summary['total_items'] ?? 0) }} inventaris
        · Rp {{ number_format($summary['total_value'] ?? 0, 0, ',', '.') }}
        · Furniture {{ $summary['category_named']['Furniture'] ?? 0 }}
        · Elektronik {{ $summary['category_named']['Elektronik'] ?? 0 }}
        · ATK {{ $summary['category_named']['ATK'] ?? 0 }}
        · Laboratorium {{ $summary['category_named']['Laboratorium'] ?? 0 }}
        · Aktif {{ $summary['aktif'] ?? 0 }}
        · Dipinjam {{ $summary['dipinjam'] ?? 0 }}
        · Maintenance {{ $summary['maintenance'] ?? 0 }}
        · Rusak {{ $summary['rusak'] ?? 0 }}
    </div>
</div>
@endsection
