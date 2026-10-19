<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Riwayat Approval Peminjaman</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 16px; color: #1F3C88; margin-bottom: 4px; }
        .meta { color: #64748b; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>
    <h1>Riwayat Approval Peminjaman</h1>
    <div class="meta">Dicetak: {{ $generatedAt->format('d/m/Y H:i') }} · Total: {{ count($rows) }}</div>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nomor Approval</th>
                <th>Tanggal</th>
                <th>Nama User</th>
                <th>Unit</th>
                <th>Barang</th>
                <th>Status</th>
                <th>Approved By</th>
                <th>Approved At</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $b)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $b->approval_number ?? '—' }}</td>
                <td>{{ ($b->approved_at ?? $b->rejected_at)?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>{{ $b->borrower_name }}</td>
                <td>{{ $b->borrower_affiliation ?: '—' }}</td>
                <td>{{ $b->inventoryItem?->name }} ({{ $b->inventoryItem?->inventory_number }})</td>
                <td>{{ $b->status?->label() }}</td>
                <td>{{ $b->approvedBy?->name ?? '—' }}</td>
                <td>{{ $b->approved_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
