@php
    $tz = (string) config('app.timezone');
@endphp
<p>Yth. {{ $borrowing->borrower_name }},</p>
<p>Persetujuan peminjaman kendaraan operasional Anda telah dibatalkan oleh Admin.</p>
<ul>
    <li>Jenis: Peminjaman Kendaraan Operasional</li>
    <li>Nomor: {{ $borrowing->approval_number ?: '—' }}</li>
    <li>Status: {{ $borrowing->status?->label() ?? 'Persetujuan Dibatalkan' }}</li>
    <li>Tujuan: {{ $borrowing->tripText() }}</li>
    <li>Jadwal: {{ $borrowing->scheduleText() }}</li>
    <li>Tanggal &amp; Waktu Pembatalan: {{ $borrowing->cancelled_at?->timezone($tz)->format('d/m/Y H:i') ?: '—' }}</li>
    <li>Dibatalkan Oleh: {{ $borrowing->cancelledBy?->name ?: '—' }}</li>
    <li>Alasan: {{ $borrowing->cancellation_reason ?: '—' }}</li>
</ul>
