<x-mail::message>
# Pengajuan Peminjaman Baru

Halo **{{ $admin->name }}**,

Ada pengajuan peminjaman inventaris baru yang menunggu persetujuan.

**Peminjam:** {{ $borrowing->borrower_name }}  
**Tanggal Pinjam:** {{ $borrowing->borrow_date?->format('d/m/Y') ?? '-' }}  
**Tanggal Kembali:** {{ $borrowing->expected_return_date?->format('d/m/Y') ?? '-' }}  
**Diajukan Oleh:** {{ $borrowing->requestedBy?->name ?? '-' }}  
**Jumlah Item:** {{ isset($items) ? $items->count() : ($item ? 1 : 0) }}

@if(isset($items) && $items->count())
**Daftar Barang:**
@foreach($items as $line)
- {{ $line->inventoryItem?->inventory_number ?? '-' }} — {{ $line->inventoryItem?->name ?? '-' }}
@endforeach
@elseif($item)
**Nomor Inventaris:** {{ $item->inventory_number ?? '-' }}  
**Nama Barang:** {{ $item->name ?? '-' }}
@endif

Silakan tinjau di aplikasi Inventaris UNDIRA.

Terima kasih.
</x-mail::message>
