<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label - {{ $inventory->inventory_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            .label-card { border: 1px solid #000 !important; box-shadow: none !important; }
        }
        .label-card { max-width: 360px; margin: 2rem auto; }
    </style>
</head>
<body class="bg-light">
<div class="container py-3 no-print text-center">
    <button type="button" class="btn btn-success me-2" onclick="window.print()">
        Cetak
    </button>
    <a href="{{ route('inventory.show', $inventory) }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="label-card card text-center p-4">
    <div class="fw-bold text-uppercase mb-1">Inventaris UNDIRA</div>
    <div class="fs-5 mb-2">{{ $inventory->name }}</div>
    <code class="fs-6 d-block mb-3">{{ $inventory->inventory_number }}</code>
    @if($inventory->qr_url)
        <img src="{{ $inventory->qr_url }}" alt="QR Code" style="width:180px;height:180px;object-fit:contain;">
    @endif
    @if($inventory->barcode_url)
        <div class="mt-3">
            <img src="{{ $inventory->barcode_url }}" alt="Barcode" style="max-width:100%;height:60px;object-fit:contain;">
        </div>
    @endif
    <div class="small text-muted mt-3">{{ $inventory->barcode }}</div>
    <div class="small mt-1">{{ $inventory->full_location }}</div>
    <div class="small">{{ $inventory->category?->name }}@if($inventory->subCategory) / {{ $inventory->subCategory->name }}@endif</div>
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
</body>
</html>
