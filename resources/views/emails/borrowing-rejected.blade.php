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

Pengajuan peminjaman inventaris Anda tidak disetujui.

**Detail Peminjaman**

**Nomor Peminjaman:** {{ $nomor }}  
**Nama Peminjam:** {{ $borrowing->borrower_name }}  
**Tanggal Peminjaman:** {{ $borrowing->plannedScheduleText('borrow_date') }}
**Tanggal Kembali:** {{ $borrowing->plannedScheduleText('expected_return_date') }}
**Lokasi Kampus:** {{ $campusLabel }}  
**Keperluan:** {{ filled($borrowing->notes) ? $borrowing->notes : '—' }}  
**Status:** Ditolak  
@if(filled($borrowing->rejection_reason))
**Alasan:** {{ $borrowing->rejection_reason }}
@endif

@if($rejected->isNotEmpty())
**INVENTARIS TIDAK DISETUJUI**

@foreach($rejected as $index => $line)
{{ $index + 1 }}. {{ $line->inventoryItem?->inventory_number ?? '—' }} — {{ $line->inventoryItem?->name ?? '—' }}@if(filled($line->rejection_reason)) ({{ $line->rejection_reason }})@endif

@endforeach
@endif

Terima kasih.

Inventaris UNDIRA  
Biro P3A  
Universitas Dian Nusantara
</x-mail::message>
