<x-mail::message>
# Pengajuan Peminjaman Baru

Halo **{{ $admin->name }}**,

Ada pengajuan peminjaman inventaris baru yang menunggu persetujuan.

**Peminjam:** {{ $borrowing->borrower_name }}  
**Nomor Inventaris:** {{ $item?->inventory_number ?? '-' }}  
**Nama Barang:** {{ $item?->name ?? '-' }}  
**Tanggal Pinjam:** {{ $borrowing->borrow_date?->format('d/m/Y') ?? '-' }}  
**Tanggal Kembali:** {{ $borrowing->expected_return_date?->format('d/m/Y') ?? '-' }}  
**Diajukan Oleh:** {{ $borrowing->requestedBy?->name ?? '-' }}

Silakan tinjau di aplikasi Inventaris UNDIRA.

Terima kasih.
</x-mail::message>
