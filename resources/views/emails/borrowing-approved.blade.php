<x-mail::message>
@php
    $logoPath = public_path('images/logo-undira.png');
    $logo = is_file($logoPath) ? $message->embed($logoPath) : null;
    $lines = isset($items) ? $items : collect();
    $nomor = $borrowing->approval_number ?: '—';
@endphp

@if($logo)
<div style="text-align:center;margin-bottom:16px;">
<img src="{{ $logo }}" alt="UNDIRA" style="height:56px;">
</div>
@endif

# Pengajuan Peminjaman Inventaris Disetujui

Yth. **{{ $borrowing->borrower_name }}**,

Kami menginformasikan bahwa pengajuan peminjaman inventaris Anda telah **DISETUJUI** oleh Admin.

**Detail Peminjaman**

**Nomor Peminjaman:** {{ $nomor }}  
**Nama Peminjam:** {{ $borrowing->borrower_name }}  
**Tanggal Peminjaman:** {{ $borrowing->borrow_date?->format('d/m/Y') ?? '—' }}  
**Tanggal Pengembalian:** {{ $borrowing->expected_return_date?->format('d/m/Y') ?? '—' }}  
**Keperluan:** {{ filled($borrowing->notes) ? $borrowing->notes : '—' }}  
**Status:** DISETUJUI

@if($lines->isNotEmpty())
**Daftar Inventaris:**

| No | Nomor Inventaris | Nama Barang | Kategori | Jumlah |
|:--:|:-----------------|:------------|:---------|-------:|
@foreach($lines as $index => $line)
| {{ $index + 1 }} | {{ str_replace('|', '/', (string) ($line->inventoryItem?->inventory_number ?? '—')) }} | {{ str_replace('|', '/', (string) ($line->inventoryItem?->name ?? '—')) }} | {{ str_replace('|', '/', (string) ($line->inventoryItem?->category?->name ?? '—')) }} | {{ (int) ($line->qty ?: 1) }} |
@endforeach
@endif

Silakan menggunakan inventaris sesuai dengan tanggal dan ketentuan peminjaman yang berlaku.

@if(!empty($borrowing->approval_number))
<x-mail::button :url="route('borrowing.approval.public', $borrowing->approval_number)">
Lihat Detail Peminjaman
</x-mail::button>
@endif

Terima kasih.

Inventaris UNDIRA  
Biro P3A  
Universitas Dian Nusantara
</x-mail::message>
