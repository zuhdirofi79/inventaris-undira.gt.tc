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

# Peminjaman Inventaris Disetujui

Halo **{{ $borrowing->borrower_name }}**,

Pengajuan peminjaman Anda telah **disetujui**.

**Nomor Approval:** {{ $borrowing->approval_number ?? '—' }}  
**Status:** {{ $borrowing->status?->label() ?? 'Disetujui' }}  
**Nomor Inventaris:** {{ $item?->inventory_number ?? '-' }}  
**Nama Barang:** {{ $item?->name ?? '-' }}  
**Tanggal Pinjam:** {{ $borrowing->borrow_date?->format('d/m/Y') ?? '-' }}  
**Tanggal Kembali:** {{ $borrowing->expected_return_date?->format('d/m/Y') ?? '-' }}

Silakan mengambil inventaris sesuai jadwal.

@if(!empty($borrowing->approval_number))
<x-mail::button :url="route('borrowing.approval.public', $borrowing->approval_number)">
Lihat Detail
</x-mail::button>
@endif

Universitas Dian Nusantara
</x-mail::message>
