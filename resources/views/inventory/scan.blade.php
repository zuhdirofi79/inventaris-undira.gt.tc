<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan QR - {{ $inventory->inventory_number }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/undira.css') }}">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="text-center mb-4">
        <img src="{{ asset('images/logo-undira.png') }}" alt="UNDIRA" style="max-height:60px;">
        <h5 class="mt-2 text-undira-blue">Inventaris UNDIRA</h5>
    </div>

    <div class="card scan-result-card card-undira shadow">
        <div class="card-header bg-undira-green text-white text-center">
            <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Hasil Scan QR</h5>
        </div>
        <div class="card-body">
            <div class="text-center mb-3">
                @if($inventory->qr_url)
                    <img src="{{ $inventory->qr_url }}" alt="QR" style="max-width:120px;">
                @endif
                <h4 class="mt-2">{{ $inventory->name }}</h4>
                <code class="fs-5">{{ $inventory->inventory_number }}</code>
            </div>

            <table class="table table-sm">
                <tr><th>Kategori</th><td>{{ $inventory->category?->name ?? '-' }}</td></tr>
                <tr><th>Sub Kategori</th><td>{{ $inventory->subCategory?->name ?? '-' }}</td></tr>
                <tr><th>Lokasi</th><td>{{ $inventory->full_location }}</td></tr>
                <tr><th>Kondisi</th><td><span class="badge bg-info">{{ $inventory->condition?->label() }}</span></td></tr>
                <tr><th>Status</th><td><span class="badge bg-secondary">{{ $inventory->status?->label() }}</span></td></tr>
                <tr><th>Merek</th><td>{{ $inventory->brand ?? '-' }}</td></tr>
                <tr><th>No. Seri</th><td>{{ $inventory->serial_number ?? '-' }}</td></tr>
            </table>

            @if($inventory->photos->isNotEmpty())
            <div class="row g-2 mt-2">
                @foreach($inventory->photos->take(4) as $photo)
                <div class="col-6">
                    <img src="{{ asset('storage/' . $photo->path) }}" class="img-fluid rounded" alt="Foto">
                </div>
                @endforeach
            </div>
            @endif

            @auth
            <div class="text-center mt-3">
                <a href="{{ route('inventory.show', $inventory) }}" class="btn btn-undira">
                    <i class="fas fa-eye me-1"></i>Lihat Detail Lengkap
                </a>
            </div>
            @else
            <div class="alert alert-info small mt-3 mb-0">
                <i class="fas fa-info-circle me-1"></i>Login untuk melihat detail lengkap dan riwayat inventaris.
            </div>
            @endauth
        </div>
    </div>

    <p class="text-center text-muted small mt-3">&copy; {{ date('Y') }} Inventaris UNDIRA</p>
</div>
</body>
</html>
