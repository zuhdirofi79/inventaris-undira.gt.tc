@php
    $room = $borrowing->room;
    $approved = $borrowing->status?->value !== 'ditolak';
@endphp
<p>Yth. {{ $borrowing->borrower_name }},</p>
<p>Pengajuan peminjaman ruangan Anda {{ $approved ? 'disetujui' : 'ditolak' }}.</p>
<ul>
    <li>Nomor: {{ $borrowing->approval_number ?: '—' }}</li>
    <li>Status: {{ $borrowing->displayLabel() }}</li>
    <li>Jenis kegiatan: {{ $borrowing->activity_type?->label() ?: '—' }}</li>
    <li>Tujuan: {{ $borrowing->purpose }}</li>
    <li>Peserta Internal: {{ $borrowing->participant_internal ? $borrowing->participantInternalCount() : 0 }} orang</li>
    <li>Peserta External: {{ $borrowing->participant_external ? $borrowing->participantExternalCount() : 0 }} orang</li>
    <li>Total Peserta: {{ $borrowing->totalParticipantCount() }} orang</li>
    <li>Ruangan: {{ $room?->full_location ?: '—' }}</li>
    <li>Kampus: {{ $borrowing->campus?->name ?: '—' }}</li>
    <li>Jadwal mulai: {{ $borrowing->starts_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?: '—' }}</li>
    <li>Jadwal selesai: {{ $borrowing->ends_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?: '—' }}</li>
    @if($approved)
        <li>Fasilitas di ruangan:
            @forelse($room?->inventoryItems ?? [] as $item)
                {{ $item->name }}@if(! $loop->last), @endif
            @empty
                tidak ada
            @endforelse
        </li>
    @else
        <li>Alasan penolakan: {{ $borrowing->rejection_reason }}</li>
    @endif
</ul>
