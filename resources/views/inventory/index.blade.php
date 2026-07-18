@extends('layouts.app')

@section('title', 'Data Inventaris - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Data Inventaris</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-boxes me-2"></i>Data Inventaris</h1>
        <p class="text-muted mb-0 small">Pilih kampus → Gedung → Lantai → Ruangan → Unit</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('inventory.create')
        <a href="{{ route('inventory.create') }}" class="btn btn-undira btn-sm"><i class="fas fa-plus me-1"></i>Tambah</a>
        @endcan
        @can('inventory.import')
        <a href="{{ route('inventory.import.index') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-import me-1"></i>Import</a>
        @endcan
        <a href="{{ route('inventory.categories') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-th-large me-1"></i>Per Kategori</a>
        <a href="{{ route('inventory.items') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>Semua Item</a>
        @can('inventory.view')
        <a href="{{ route('inventory.trash') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-trash me-1"></i>Trash</a>
        @endcan
        @can('inventory.export')
        <div class="btn-group btn-group-sm">
            <a href="{{ route('inventory.export.excel') }}" class="btn btn-outline-success"><i class="fas fa-file-excel"></i><span class="d-none d-sm-inline"> Excel</span></a>
            <a href="{{ route('inventory.export.csv') }}" class="btn btn-outline-success"><i class="fas fa-file-csv"></i><span class="d-none d-sm-inline"> CSV</span></a>
            <a href="{{ route('inventory.export.pdf') }}" class="btn btn-outline-danger"><i class="fas fa-file-pdf"></i><span class="d-none d-sm-inline"> PDF</span></a>
        </div>
        @endcan
    </div>
</div>

<div class="row g-3">
    @forelse($campuses as $campus)
    @php
        $s = $stats[$campus->id] ?? [
            'total_units' => 0, 'total_items' => 0, 'floors' => 0, 'rooms' => 0,
            'available' => 0, 'borrowed' => 0, 'maintenance' => 0, 'damaged' => 0,
        ];
    @endphp
    <div class="col-12 col-md-6 col-lg-4">
        <a href="{{ route('inventory.campus', $campus) }}" class="text-decoration-none">
            <div class="card card-undira campus-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="min-w-0">
                            <h4 class="mb-1 text-undira-blue text-break">{{ $campus->name }}</h4>
                            <code class="small">{{ $campus->code }}</code>
                        </div>
                        <span class="badge bg-success">{{ number_format($s['total_units']) }} Unit</span>
                    </div>
                    <div class="row g-2 small text-muted mb-3">
                        <div class="col-6"><i class="fas fa-cube me-1"></i>{{ number_format($s['total_items']) }} Item</div>
                        <div class="col-6"><i class="fas fa-building me-1"></i>{{ $campus->buildings_count }} Gedung</div>
                        <div class="col-6"><i class="fas fa-layer-group me-1"></i>{{ number_format($s['floors']) }} Lantai</div>
                        <div class="col-6"><i class="fas fa-door-open me-1"></i>{{ number_format($s['rooms']) }} Ruangan</div>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge text-bg-success">Tersedia {{ $s['available'] }}</span>
                        <span class="badge text-bg-warning">Pinjam {{ $s['borrowed'] }}</span>
                        <span class="badge text-bg-info">Maint. {{ $s['maintenance'] }}</span>
                        <span class="badge text-bg-danger">Rusak {{ $s['damaged'] }}</span>
                    </div>
                </div>
            </div>
        </a>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-light text-center">Belum ada kampus aktif.</div>
    </div>
    @endforelse
</div>
@endsection
