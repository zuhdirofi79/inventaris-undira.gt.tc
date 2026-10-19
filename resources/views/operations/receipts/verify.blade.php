@php
    $p = $payload;
    $isApproval = ($p['purpose'] ?? '') === 'approval';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $p['title'] ?? 'Verifikasi Receipt' }} — Inventaris UNDIRA</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 24px 16px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 8px 24px rgba(15,23,42,.06); overflow: hidden; }
        .card-head { background: #1F3C88; color: #fff; padding: 18px 20px; }
        .card-head h1 { margin: 0; font-size: 1.15rem; font-weight: 700; }
        .card-head p { margin: 6px 0 0; font-size: .85rem; opacity: .9; }
        .badge { display: inline-block; margin-top: 10px; padding: 4px 10px; border-radius: 999px; background: rgba(255,255,255,.18); font-size: .75rem; font-weight: 700; }
        .body { padding: 18px 20px 22px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: .9rem; }
        th { width: 38%; color: #64748b; font-weight: 600; }
        .section { margin-top: 16px; }
        .section h2 { margin: 0 0 8px; font-size: .95rem; color: #1F3C88; }
        .note { margin-top: 16px; font-size: .8rem; color: #64748b; line-height: 1.45; }
        .items { width: 100%; margin-top: 8px; font-size: .82rem; }
        .items th { background: #f8fafc; padding: 6px 8px; border: 1px solid #e2e8f0; }
        .items td { padding: 6px 8px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-head">
        <h1>{{ $p['title'] }}</h1>
        <p>{{ $p['kind_label'] }} · Universitas Dian Nusantara</p>
        <span class="badge">{{ $isApproval ? 'Verifikasi Approval' : 'Verifikasi Peminjam' }}</span>
    </div>
    <div class="body">
        <table>
            <tr><th>Nomor Approval</th><td>{{ $p['approval_number'] }}</td></tr>
            <tr><th>Nomor Pengajuan</th><td>{{ $p['request_number'] }}</td></tr>
            <tr><th>Peminjam</th><td>{{ $p['borrower_name'] }}</td></tr>
            <tr><th>Unit</th><td>{{ $p['unit'] }}</td></tr>
            <tr><th>Status</th><td>{{ $p['status'] }}</td></tr>
            <tr><th>Jadwal</th><td>{{ $p['schedule'] }}</td></tr>
            @if($isApproval)
                <tr><th>{{ $p['decision_label'] }}</th><td>{{ $p['approver_name'] ?: 'Tidak tercatat' }}</td></tr>
                <tr><th>Waktu Keputusan</th><td>{{ $p['approved_at'] ?: '—' }}</td></tr>
            @endif
        </table>

        @if(!empty($p['detail']) && is_array($p['detail']))
            <div class="section">
                <h2>Rincian</h2>
                <table>
                    @foreach($p['detail'] as $label => $value)
                        <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
                    @endforeach
                </table>
            </div>
        @endif

        @if(!empty($p['item_counts']))
            <div class="section">
                <h2>Ringkasan Persetujuan Inventaris</h2>
                <table>
                    <tr><th>Disetujui</th><td>{{ $p['item_counts']['disetujui'] }}</td></tr>
                    <tr><th>Ditolak</th><td>{{ $p['item_counts']['ditolak'] }}</td></tr>
                    <tr><th>Menunggu</th><td>{{ $p['item_counts']['menunggu'] }}</td></tr>
                </table>
            </div>
        @endif

        @if(!empty($p['items']))
            <div class="section">
                <h2>Status per Inventaris</h2>
                <table class="items">
                    <thead>
                        <tr><th>Nomor</th><th>Nama</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($p['items'] as $row)
                            <tr>
                                <td>{{ $row['number'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="note">
            Halaman ini bersifat verifikasi saja (read-only). Tidak ada data pribadi tambahan yang ditampilkan,
            dan status transaksi tidak dapat diubah melalui tautan ini.
        </p>
    </div>
</div>
</body>
</html>
