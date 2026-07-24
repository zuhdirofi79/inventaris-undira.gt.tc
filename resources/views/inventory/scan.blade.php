<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Inventaris - {{ $inventory->inventory_number }}</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/undira.css') }}">
    <link rel="stylesheet" href="{{ asset('css/undira-ui.css') }}">
    <style>
        body { background: #F3F5F9; }
        .scan-hero-photo { max-height: 220px; object-fit: cover; width: 100%; border-radius: 12px; }
        .scan-meta th { width: 42%; color: #64748B; font-weight: 600; font-size: .85rem; }
        .timeline-scan { border-left: 2px solid #E6EAF2; margin-left: .5rem; padding-left: 1rem; }
        .timeline-scan .item { position: relative; padding-bottom: 1rem; }
        .timeline-scan .item::before {
            content: ""; position: absolute; left: -1.35rem; top: .35rem;
            width: .65rem; height: .65rem; border-radius: 50%; background: #2E7D32;
            box-shadow: 0 0 0 3px #E8F5E9;
        }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 720px;">
    <div class="text-center mb-4">
        <img src="{{ asset('images/logo-undira.png') }}" alt="UNDIRA" style="max-height:56px;">
        <h1 class="h5 mt-2 mb-0 text-undira-blue fw-bold">Inventaris UNDIRA</h1>
        <p class="text-muted small mb-0">Universitas Dian Nusantara</p>
    </div>

    <div class="card card-undira shadow-sm border-0 mb-3">
        <div class="card-header bg-undira-green text-white text-center py-3">
            <h2 class="h5 mb-0"><i class="bi bi-qr-code-scan me-2"></i>Hasil Scan QR</h2>
        </div>
        <div class="card-body">
            @php $primary = $inventory->photos->firstWhere('is_primary', true) ?? $inventory->photos->first(); @endphp
            @if($primary)
                <img src="{{ asset('storage/'.$primary->path) }}" alt="Foto {{ $inventory->name }}" class="scan-hero-photo mb-3">
            @endif

            <div class="text-center mb-3">
                <h3 class="h4 mb-1">{{ $inventory->name }}</h3>
                <code class="fs-6">{{ $inventory->inventory_number }}</code>
                <div class="mt-2 d-flex justify-content-center gap-2 flex-wrap">
                    <x-inventory.condition-badge :condition="$inventory->condition" />
                    <x-inventory.status-badge :status="$inventory->status" />
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm scan-meta mb-0">
                    <tbody>
                        <tr><th>Kategori</th><td>{{ $inventory->category?->name ?? '—' }}</td></tr>
                        <tr><th>Subkategori</th><td>{{ $inventory->subCategory?->name ?? '—' }}</td></tr>
                        <tr><th>Kampus</th><td>{{ $inventory->room?->floor?->building?->campus?->name ?? '—' }}</td></tr>
                        <tr><th>Gedung</th><td>{{ $inventory->room?->floor?->building?->name ?? '—' }}</td></tr>
                        <tr><th>Lantai</th><td>{{ $inventory->room?->floor?->name ?? '—' }}</td></tr>
                        <tr><th>Ruangan</th><td>{{ $inventory->room?->name ?? '—' }}</td></tr>
                        <tr><th>Unit Kerja</th><td>{{ $inventory->orgUnit?->name ?? '—' }}</td></tr>
                        <tr><th>Jenis Pengadaan</th><td>{{ $inventory->procurementType?->name ?? '—' }}</td></tr>
                        <tr><th>Tahun Pengadaan</th><td>{{ $inventory->purchase_year ?? '—' }}</td></tr>
                        <tr><th>Merek</th><td>{{ $inventory->brand ?? '—' }}</td></tr>
                        <tr><th>Dibuat</th><td>{{ $inventory->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }} WIB</td></tr>
                        <tr><th>Diperbarui</th><td>{{ $inventory->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }} WIB</td></tr>
                        @auth
                        <tr><th>No. Seri</th><td>{{ $inventory->serial_number ?? '—' }}</td></tr>
                        @else
                        <tr><th>No. Seri</th><td><span class="text-muted">Login untuk melihat</span></td></tr>
                        @endauth
                    </tbody>
                </table>
            </div>

            @if($inventory->photos->count() > 1)
            <div class="row g-2 mt-3">
                @foreach($inventory->photos->take(6) as $photo)
                <div class="col-4">
                    <img src="{{ asset('storage/'.$photo->path) }}" class="img-fluid rounded" alt="Foto">
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    <div class="card card-undira shadow-sm border-0 mb-3">
        <div class="card-header"><strong><i class="bi bi-arrow-left-right me-2 text-undira-green"></i>Riwayat Perpindahan</strong></div>
        <div class="card-body">
            @forelse($inventory->mutations as $mutation)
                <div class="small mb-2 pb-2 border-bottom">
                    <div class="fw-semibold">{{ $mutation->created_at->format('d/m/Y H:i') }}</div>
                    <div>{{ $mutation->fromRoom?->full_location ?? '—' }} → {{ $mutation->toRoom?->full_location ?? '—' }}</div>
                    @if($mutation->reason)<div class="text-muted">{{ $mutation->reason }}</div>@endif
                </div>
            @empty
                <p class="text-muted small mb-0">Belum ada riwayat perpindahan.</p>
            @endforelse
        </div>
    </div>

    <div class="card card-undira shadow-sm border-0 mb-3">
        <div class="card-header"><strong><i class="bi bi-wrench-adjustable me-2 text-undira-green"></i>Riwayat Maintenance</strong></div>
        <div class="card-body">
            @forelse($inventory->maintenances as $maint)
                <div class="small mb-2 pb-2 border-bottom">
                    <div class="fw-semibold">{{ $maint->service_date?->format('d/m/Y') ?? $maint->created_at?->format('d/m/Y') }}</div>
                    <div>{{ $maint->status?->label() ?? '—' }}@if($maint->vendor) · {{ $maint->vendor->name }}@endif</div>
                </div>
            @empty
                <p class="text-muted small mb-0">Belum ada riwayat maintenance.</p>
            @endforelse
        </div>
    </div>

    @auth
        @can('inventory.view')
        <div class="text-center mb-3">
            <a href="{{ route('inventory.show', $inventory) }}" class="btn btn-undira">
                <i class="bi bi-eye me-1"></i>Buka di Aplikasi
            </a>
        </div>
        @endcan
    @else
        <div class="alert alert-light border small">
            Login untuk melihat nomor seri lengkap dan mengelola inventaris.
        </div>
    @endauth

    <p class="text-center text-muted small mt-2 mb-0">&copy; {{ date('Y') }} Inventaris UNDIRA</p>
</div>
</body>
</html>
