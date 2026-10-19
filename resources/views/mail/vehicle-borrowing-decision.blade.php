@php
    $approved = $borrowing->status?->value !== 'ditolak';
@endphp
<p>Yth. {{ $borrowing->borrower_name }},</p>
<p>Pengajuan peminjaman kendaraan operasional Anda {{ $approved ? 'disetujui' : 'ditolak' }}.</p>
<ul>
    <li>Nomor: {{ $borrowing->approval_number ?: '—' }}</li>
    <li>Status: {{ $borrowing->displayLabel() }}</li>
    <li>Unit: {{ $borrowing->unit ?: '—' }}</li>
    <li>Kampus: {{ $borrowing->campus?->name ?: '—' }}</li>
    <li>Rute: {{ $borrowing->origin ?: ($borrowing->route?->name ?: '—') }}</li>
    <li>Tujuan: {{ $borrowing->destination ?: '—' }}</li>
    @if($borrowing->destinationMapsUrl())
        <li>Link Google Maps Tujuan: <a href="{{ $borrowing->destinationMapsUrl() }}" target="_blank" rel="noopener noreferrer">{{ $borrowing->destinationMapsUrl() }}</a></li>
    @endif
    <li>Keperluan: {{ $borrowing->purpose }}</li>
    <li>Jenis pengantaran: {{ $borrowing->service_type?->label() ?: '—' }}</li>
    <li>Jumlah penumpang: {{ $borrowing->passenger_count ?: '—' }}</li>
    <li>Jadwal: {{ $borrowing->scheduleText() }}</li>
    @if($borrowing->passengerRows() !== [])
        <li>Daftar penumpang:
            <ul>
                @foreach($borrowing->passengerRows() as $passenger)
                    <li>{{ $passenger['name'] }}@if($passenger['unit'] !== '') — {{ $passenger['unit'] }}@endif @if($passenger['title'] !== '') — {{ $passenger['title'] }}@endif</li>
                @endforeach
            </ul>
        </li>
    @endif
    @if($approved)
        <li>Penugasan:
            @if($borrowing->assigned_service === \App\Enums\VehicleServiceType::GoCar)
                GoCar
            @else
                {{ $borrowing->vehicle?->label() ?: '—' }}
                @if($borrowing->driver)
                    — pengemudi {{ $borrowing->driver->displayName() }}
                    @if($borrowing->driver->displayPhone())
                        ({{ $borrowing->driver->displayPhone() }})
                    @endif
                @endif
            @endif
        </li>
        <li>Disetujui oleh: {{ $borrowing->approvedBy?->name ?: '—' }}</li>
    @else
        <li>Alasan penolakan: {{ $borrowing->rejection_reason }}</li>
        <li>Ditolak oleh: {{ $borrowing->approvedBy?->name ?: '—' }}</li>
        <li>Waktu keputusan: {{ $borrowing->rejected_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?: '—' }}</li>
    @endif
</ul>
