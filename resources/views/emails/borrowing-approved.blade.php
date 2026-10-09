<x-mail::message>
@php
    $logoPath = public_path('images/logo-undira.png');
    $logo = is_file($logoPath) ? $message->embed($logoPath) : null;
    $lines = isset($items) ? $items : collect();
    $approvedStatuses = [
        \App\Enums\BorrowingItemStatus::Disetujui,
        \App\Enums\BorrowingItemStatus::Dipinjam,
        \App\Enums\BorrowingItemStatus::Dikembalikan,
    ];
    $approved = $lines->filter(fn ($line) => in_array($line->status, $approvedStatuses, true));
    $rejected = $lines->filter(fn ($line) => $line->status === \App\Enums\BorrowingItemStatus::Ditolak);
    $isPartial = $approved->isNotEmpty() && $rejected->isNotEmpty();
    $nomor = $borrowing->approval_number ?: '—';
    $campusLabel = $borrowing->campusSummary();
@endphp

@if($logo)
<div style="text-align:center;margin-bottom:16px;">
<img src="{{ $logo }}" alt="UNDIRA" style="height:56px;">
</div>
@endif

# Hasil Persetujuan Peminjaman Inventaris

Yth. **{{ $borrowing->borrower_name }}**,

@if($isPartial)
Pengajuan peminjaman inventaris Anda telah diproses oleh Admin.

Sebagian inventaris pada pengajuan Anda telah disetujui.
@else
Pengajuan peminjaman inventaris Anda telah diproses oleh Admin.

Seluruh inventaris pada pengajuan Anda telah disetujui.
@endif

**Detail Peminjaman**

**Nomor Peminjaman:** {{ $nomor }}  
**Nama Peminjam:** {{ $borrowing->borrower_name }}  
**Tanggal Peminjaman:** {{ $borrowing->plannedScheduleText('borrow_date') }}  
**Tanggal Kembali:** {{ $borrowing->plannedScheduleText('expected_return_date') }}  
**Lokasi Kampus:** {{ $campusLabel }}  
**Keperluan:** {{ filled($borrowing->notes) ? $borrowing->notes : '—' }}  
**Status:** {{ $isPartial ? 'Disetujui sebagian' : 'Disetujui' }}  
@if($isPartial)
**Ringkasan:** {{ $approved->count() }} inventaris disetujui, {{ $rejected->count() }} inventaris ditolak.
@endif

@if($approved->isNotEmpty())
**INVENTARIS DISETUJUI**

@foreach($approved as $index => $line)
{{ $index + 1 }}. {{ $line->inventoryItem?->inventory_number ?? '—' }} — {{ $line->inventoryItem?->name ?? '—' }}

@endforeach
@endif

@if($rejected->isNotEmpty())
**INVENTARIS TIDAK DISETUJUI**

@foreach($rejected as $index => $line)
{{ $index + 1 }}. {{ $line->inventoryItem?->inventory_number ?? '—' }} — {{ $line->inventoryItem?->name ?? '—' }}@if(filled($line->rejection_reason)) ({{ $line->rejection_reason }})@endif

@endforeach
@endif

Silakan mengambil inventaris yang **disetujui** sesuai ketentuan. Barang baru tercatat sebagai dipinjam setelah serah terima. Inventaris yang ditolak tidak dapat dipinjam pada transaksi ini.

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
