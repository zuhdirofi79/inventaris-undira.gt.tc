<x-mail::message>
# Pengajuan Peminjaman Baru

Halo **{{ $admin->name }}**,

Ada pengajuan peminjaman inventaris baru yang menunggu persetujuan.

**Peminjam:** {{ $borrowing->borrower_name }}  
**Tanggal Pinjam:** {{ $borrowing->plannedScheduleText('borrow_date') }}
**Tanggal Kembali:** {{ $borrowing->plannedScheduleText('expected_return_date') }}
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
