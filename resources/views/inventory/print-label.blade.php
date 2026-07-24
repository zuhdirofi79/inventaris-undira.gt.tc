<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label - {{ $inventory->inventory_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f5f7fa; color: #172033; }
        .label-card {
            max-width: 380px;
            margin: 1.25rem auto;
            background: #fff;
            border: 1px solid #d9e0ea;
            border-radius: 10px;
            padding: 1.25rem 1.1rem;
        }
        .label-title { font-weight: 800; letter-spacing: 0.04em; font-size: 0.95rem; }
        .label-name { font-size: 1.15rem; font-weight: 700; line-height: 1.25; }
        .label-number { font-size: 0.95rem; color: #b91c1c; font-weight: 700; word-break: break-all; }
        .label-qr {
            width: 160px;
            height: 160px;
            object-fit: contain;
            display: block;
            margin: 0.75rem auto 0.35rem;
        }
        .label-meta { font-size: 0.8rem; color: #5f6b7a; line-height: 1.4; }
        .code-fallback {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 0.75rem;
            color: #64748b;
            font-size: 0.85rem;
            margin: 0.5rem 0;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; margin: 0; }
            .label-card {
                margin: 0;
                max-width: none;
                border: 1px solid #000;
                border-radius: 0;
                box-shadow: none;
                page-break-inside: avoid;
            }
            .label-qr { width: 140px; height: 140px; }
        }
    </style>
</head>
<body>
<div class="container py-3 no-print text-center">
    <button type="button" class="btn btn-success me-2" id="btnPrint" onclick="window.print()">Cetak</button>
    <a href="{{ route('inventory.show', $inventory) }}" class="btn btn-outline-secondary" data-no-loader="1">Kembali</a>
</div>

@php $hasQr = filled($inventory->qr_url); @endphp

<div class="label-card text-center">
    <div class="label-title text-uppercase mb-1">Inventaris UNDIRA</div>
    <div class="label-name mb-1">{{ $inventory->name }}</div>
    <div class="label-number mb-2">{{ $inventory->inventory_number }}</div>

    @if($hasQr)
        <img
            src="{{ $inventory->qr_url }}"
            alt="QR Code {{ $inventory->inventory_number }}"
            class="label-qr"
            width="160"
            height="160"
            loading="eager"
            decoding="async"
        >
    @else
        <div class="code-fallback">QR Code belum dapat ditampilkan.</div>
    @endif

    <div class="small fw-semibold mt-2">{{ $inventory->inventory_number }}</div>
    <div class="label-meta mt-2">Ruangan: {{ $inventory->room?->name ?? $inventory->full_location }}</div>
    <div class="label-meta">PIC: {{ $inventory->picDisplayName() ?: '—' }}</div>
    <div class="label-meta">
        {{ $inventory->category?->name }}@if($inventory->subCategory) / {{ $inventory->subCategory->name }}@endif
    </div>
</div>

<script>
(function () {
    var ready = {{ $hasQr ? 'true' : 'false' }};
    var btn = document.getElementById('btnPrint');
    if (!ready && btn) {
        btn.addEventListener('click', function (e) {
            if (!window.confirm('QR Code belum lengkap. Tetap cetak?')) {
                e.preventDefault();
            }
        });
        return;
    }
    window.addEventListener('load', function () {
        window.setTimeout(function () { window.print(); }, 350);
    });
})();
</script>
</body>
</html>
