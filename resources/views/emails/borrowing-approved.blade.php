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
@endphp

@if($logo)
<div style="text-align:center;margin-bottom:16px;">
<img src="{{ $logo }}" alt="UNDIRA" style="height:56px;">
</div>
@endif

# Hasil Persetujuan Peminjaman Inventaris

Yth. **{{ $borrowing->borrower_name }}**,

@if($isPartial)
Kami menginformasikan bahwa pengajuan peminjaman inventaris Anda telah **DISETUJUI SEBAGIAN**.
@else
Kami menginformasikan bahwa pengajuan peminjaman inventaris Anda telah **DISETUJUI** oleh Admin.
@endif

**Detail Peminjaman**

**Nomor Peminjaman:** {{ $nomor }}  
**Nama Peminjam:** {{ $borrowing->borrower_name }}  
**Tanggal Peminjaman (Rencana):** {{ $borrowing->borrow_date?->format('d/m/Y') ?? '—' }}  
**Tanggal Pengembalian (Rencana):** {{ $borrowing->expected_return_date?->format('d/m/Y') ?? '—' }}  
**Keperluan:** {{ filled($borrowing->notes) ? $borrowing->notes : '—' }}  
**Status Transaksi:** {{ $isPartial ? 'DISETUJUI (sebagian item ditolak)' : 'DISETUJUI' }}  
@if($isPartial)
**Ringkasan:** {{ $approved->count() }} inventaris disetujui, {{ $rejected->count() }} inventaris ditolak.
@endif

@if($approved->isNotEmpty())
**Inventaris Disetujui:**

| No | Nomor Inventaris | Nama Barang | Kategori | Jumlah |
|:--:|:-----------------|:------------|:---------|-------:|
@foreach($approved as $index => $line)
| {{ $index + 1 }} | {{ str_replace('|', '/', (string) ($line->inventoryItem?->inventory_number ?? '—')) }} | {{ str_replace('|', '/', (string) ($line->inventoryItem?->name ?? '—')) }} | {{ str_replace('|', '/', (string) ($line->inventoryItem?->category?->name ?? '—')) }} | {{ (int) ($line->qty ?: 1) }} |
@endforeach
@endif

@if($rejected->isNotEmpty())
**Inventaris Ditolak:**

| No | Nomor Inventaris | Nama Barang | Alasan |
|:--:|:-----------------|:------------|:-------|
@foreach($rejected as $index => $line)
| {{ $index + 1 }} | {{ str_replace('|', '/', (string) ($line->inventoryItem?->inventory_number ?? '—')) }} | {{ str_replace('|', '/', (string) ($line->inventoryItem?->name ?? '—')) }} | {{ str_replace('|', '/', (string) ($line->rejection_reason ?: '—')) }} |
@endforeach
@endif

Silakan mengambil inventaris yang disetujui sesuai ketentuan. Barang baru tercatat sebagai dipinjam setelah serah terima.

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
