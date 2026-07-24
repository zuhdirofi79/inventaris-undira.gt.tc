@extends('layouts.app')

@section('title', 'Tambah Peminjaman - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Pinjam Inventaris"
    subtitle="Pilih barang dulu, cek detailnya, lalu isi data peminjam. Tombol Simpan aktif setelah barang siap dipinjam."
    icon="bi bi-box-arrow-right"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Peminjaman', 'url' => route('borrowings.index')],
        ['label' => 'Tambah'],
    ]"
    :back-route="route('borrowings.index')"
    back-label="Daftar Peminjaman"
    :list-back="true"
/>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('borrowings.store') }}" method="POST" id="borrowForm" data-no-loading>
    @csrf
    <input type="hidden" name="status" value="dipinjam">

    <div class="card card-undira mb-3">
        <div class="card-header">
            <strong>Langkah 1 — Pilih barang</strong>
            <div class="small text-muted fw-normal mt-1">
                Dua cara: telusuri lokasi (Kampus → Gedung → Lantai → Ruangan → Kategori → Sub → Barang),
                atau cari cepat lewat nomor / nama / QR / barcode / serial.
            </div>
        </div>
        <div class="card-body">
            <x-inventory-selector
                name="inventory_item_id"
                id="borrow-inventory-selector"
                :selected-id="$selectedDetail['id'] ?? null"
                :selected-label="isset($selectedDetail) ? ($selectedDetail['inventory_number'].' — '.$selectedDetail['name']) : null"
                status-filter="aktif"
                require-borrowable
                show-qr-scan
                modes="both"
                submit-control="#btnBorrowSubmit"
                help-text="Ketik minimal 2 huruf/angka: nomor inventaris, nama, barcode, atau serial. Bisa juga scan QR."
                label="Barang yang dipinjam"
            />
            <div id="borrowReadyHint" class="alert alert-success mt-3 mb-0 d-none" role="status">
                <i class="fas fa-check-circle me-1"></i>Barang siap dipinjam. Lanjut isi data peminjam di bawah, lalu Simpan.
            </div>
        </div>
    </div>

    <div class="card card-undira" id="borrowerCard">
        <div class="card-header">
            <strong>Langkah 2 — Data peminjam</strong>
            <div class="small text-muted fw-normal mt-1">Isi identitas orang yang membawa barang.</div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Peminjam <span class="text-danger">*</span></label>
                    <input type="text" name="borrower_name" class="form-control" value="{{ old('borrower_name') }}" required autocomplete="name">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="borrower_email" class="form-control" value="{{ old('borrower_email') }}" autocomplete="email">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Telepon</label>
                    <input type="text" name="borrower_phone" class="form-control" value="{{ old('borrower_phone') }}" autocomplete="tel">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tanggal Pinjam <span class="text-danger">*</span></label>
                    <input type="date" name="borrow_date" class="form-control" value="{{ old('borrow_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tanggal Kembali <span class="text-danger">*</span></label>
                    <input type="date" name="expected_return_date" class="form-control" value="{{ old('expected_return_date') }}" required>
                </div>
                <div class="col-12 mb-0">
                    <label class="form-label">Keperluan</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: dipakai kuliah / rapat / praktikum…">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-undira" id="btnBorrowSubmit" disabled
                    title="Pilih inventaris yang statusnya Aktif terlebih dahulu">
                <i class="fas fa-save me-1"></i>Simpan Peminjaman
            </button>
            <a href="{{ route('borrowings.index') }}" class="btn btn-outline-secondary">Batal</a>
            <span class="small text-muted" id="borrowSubmitHint">Tombol Simpan terkunci sampai barang yang dipilih siap dipinjam.</span>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    var $hint = $('#borrowReadyHint');
    var $submitHint = $('#borrowSubmitHint');
    $('#borrow-inventory-selector').on('inventory-selector:selected', function (_e, detail, meta) {
        var ready = !!(detail && detail.borrowable && !(meta && meta.blocked));
        $hint.toggleClass('d-none', !ready);
        if ($submitHint.length) {
            $submitHint.text(ready
                ? 'Barang valid. Anda bisa menyimpan peminjaman.'
                : 'Tombol Simpan terkunci sampai barang yang dipilih siap dipinjam.');
        }
    });
})(window.jQuery);
</script>
@endpush
