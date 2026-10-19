@php
    $tz = (string) config('app.timezone');
@endphp
<p>Yth. {{ $borrowing->driver?->displayName() ?: 'Pengemudi' }},</p>
<p>Anda ditugaskan sebagai Driver untuk peminjaman kendaraan operasional berikut.</p>
<ul>
    <li>Nomor: {{ $borrowing->approval_number ?: '—' }}</li>
    <li>Nama peminjam: {{ $borrowing->borrower_name }}</li>
    <li>Telepon peminjam: {{ $borrowing->borrower_phone ?: '—' }}</li>
    <li>Unit: {{ $borrowing->unit ?: '—' }}</li>
    <li>Kampus / lokasi peminjaman: {{ $borrowing->campus?->name ?: '—' }}</li>
    <li>Rute: {{ $borrowing->origin ?: ($borrowing->route?->name ?: '—') }}</li>
    <li>Tujuan: {{ $borrowing->destination ?: '—' }}</li>
    @if($borrowing->destinationMapsUrl())
        <li>Link Google Maps Tujuan: <a href="{{ $borrowing->destinationMapsUrl() }}" target="_blank" rel="noopener noreferrer">{{ $borrowing->destinationMapsUrl() }}</a></li>
    @endif
    <li>Jenis pengantaran: {{ $borrowing->assigned_service?->label() ?: ($borrowing->service_type?->label() ?: '—') }}</li>
    <li>Jumlah penumpang: {{ $borrowing->passenger_count ?: '—' }}</li>
    <li>Jadwal mulai: {{ $borrowing->starts_at?->timezone($tz)->format('d/m/Y H:i') ?: '—' }}</li>
    <li>Jadwal selesai: {{ $borrowing->ends_at?->timezone($tz)->format('d/m/Y H:i') ?: '—' }}</li>
    <li>Kendaraan: {{ $borrowing->vehicle?->label() ?: '—' }}</li>
    <li>Ditetapkan oleh: {{ $borrowing->approvedBy?->name ?: '—' }}</li>
</ul>
<p>Silakan buka aplikasi LOADOUT untuk melihat detail penugasan.</p>
