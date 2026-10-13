<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Approval {{ $number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 980px; margin: 2rem auto; padding: 0 12px; }
        .brand { color: #1F3C88; }
        .card { border: 0; box-shadow: 0 8px 24px rgba(15,23,42,.06); border-radius: 14px; }
        .qr img { width: 140px; height: 140px; max-width: 100%; }
        .approval-items th, .approval-items td { vertical-align: top; overflow-wrap: anywhere; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="text-center mb-3">
        @if(!empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="UNDIRA" style="height:52px">
        @endif
        <h1 class="h4 brand mt-2 mb-0">Detail Approval Peminjaman</h1>
        <div class="text-muted small">Universitas Dian Nusantara</div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="text-muted small">Nomor Approval</div>
                    <div class="fs-5 fw-bold">{{ $number }}</div>
                    <span class="badge bg-{{ $statusColor }} mt-2">{{ $statusLabel }}</span>
                </div>
                @if(!empty($qrDataUri))
                    <div class="qr text-center">
                        <img src="{{ $qrDataUri }}" alt="QR Approval">
                    </div>
                @endif
            </div>
            <hr>
            <table class="table table-sm mb-3">
                @foreach($rows as [$label, $value])
                    <tr>
                        <th style="width:40%">{{ $label }}</th>
                        <td>{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
            <h2 class="h6">{{ $tableTitle }}</h2>
            <div class="table-responsive">
                <table class="table table-sm approval-items mb-0">
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as $line)
                            <tr>
                                @foreach($line as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-center">
        <a class="btn btn-outline-primary btn-sm" href="{{ $receiptUrl }}">Lihat Receipt</a>
        <a class="btn btn-primary btn-sm" href="{{ $pdfUrl }}">Download PDF</a>
    </div>
</div>
</body>
</html>
