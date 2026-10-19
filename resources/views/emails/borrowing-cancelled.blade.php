<x-mail::message>
@php
    $logoPath = public_path('images/logo-undira.png');
    $logo = is_file($logoPath) ? $message->embed($logoPath) : null;
    $nomor = $borrowing->approval_number ?: '—';
    $tz = (string) config('app.timezone');
@endphp

@if($logo)
<div style="text-align:center;margin-bottom:16px;">
<img src="{{ $logo }}" alt="UNDIRA" style="height:56px;">
</div>
@endif

# Persetujuan Peminjaman Inventaris Dibatalkan

Yth. **{{ $borrowing->borrower_name }}**,

Persetujuan peminjaman inventaris Anda telah dibatalkan oleh Admin.

**Detail Pembatalan**

**Jenis:** Peminjaman Inventaris  
**Nomor:** {{ $nomor }}  
**Status:** {{ $borrowing->status?->label() ?? 'Persetujuan Dibatalkan' }}  
**Tanggal & Waktu Pembatalan:** {{ $borrowing->cancelled_at?->timezone($tz)->format('d/m/Y H:i') ?: '—' }}  
**Dibatalkan Oleh:** {{ $borrowing->cancelledBy?->name ?: '—' }}  
**Alasan:** {{ $borrowing->cancellation_reason ?: '—' }}

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
