<x-mail::message>
@php
    $logoPath = public_path('images/logo-undira.png');
    $logo = is_file($logoPath) ? $message->embed($logoPath) : null;
    $lines = isset($items) ? $items : collect();
    $rejected = $lines->filter(fn ($line) => $line->status === \App\Enums\BorrowingItemStatus::Ditolak);
    $nomor = $borrowing->approval_number ?: '—';
    $campusLabel = $borrowing->campusSummary();
@endphp

@if($logo)
<div style="text-align:center;margin-bottom:16px;">
<img src="{{ $logo }}" alt="UNDIRA" style="height:56px;">
</div>
@endif

# Pengajuan Peminjaman Inventaris Tidak Disetujui

Yth. **{{ $borrowing->borrower_name }}**,

Pengajuan peminjaman inventaris Anda belum dapat disetujui.

**Detail Peminjaman**

**Nomor Peminjaman:** {{ $nomor }}  
**Nama Peminjam:** {{ $borrowing->borrower_name }}  
**Tanggal Peminjaman:** {{ $borrowing->borrow_date?->format('d/m/Y') ?? '—' }}  
**Tanggal Kembali:** {{ $borrowing->expected_return_date?->format('d/m/Y') ?? '—' }}  
**Lokasi Kampus:** {{ $campusLabel }}  
**Keperluan:** {{ filled($borrowing->notes) ? $borrowing->notes : '—' }}  
**Status:** Ditolak  
@if(filled($borrowing->rejection_reason))
**Alasan:** {{ $borrowing->rejection_reason }}
@endif

@if($rejected->isNotEmpty())
**Inventaris Ditolak:**

@foreach($rejected as $index => $line)
- {{ $line->inventoryItem?->inventory_number ?? '—' }} — {{ $line->inventoryItem?->name ?? '—' }}@if(filled($line->rejection_reason)) ({{ $line->rejection_reason }})@endif

@endforeach
@endif

Terima kasih.

Inventaris UNDIRA  
Biro P3A  
Universitas Dian Nusantara
</x-mail::message>
