<x-mail::message>
@php
    $logoPath = public_path('images/logo-undira.png');
    $logo = is_file($logoPath) ? $message->embed($logoPath) : null;
@endphp

@if($logo)
<div style="text-align:center;margin-bottom:16px;">
<img src="{{ $logo }}" alt="UNDIRA" style="height:56px;">
</div>
@endif

# Peminjaman Inventaris Ditolak

Halo **{{ $borrowing->borrower_name }}**,

Mohon maaf.

Pengajuan peminjaman inventaris tidak dapat disetujui.

**Status:** Ditolak  
**Alasan:**  
{{ $borrowing->rejection_reason }}

Terima kasih.

Universitas Dian Nusantara
</x-mail::message>
