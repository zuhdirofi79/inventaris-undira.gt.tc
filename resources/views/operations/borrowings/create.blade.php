@extends('layouts.app')

@section('title', 'Tambah Peminjaman - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('borrowings.index') }}">Peminjaman</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="page-header-undira"><h1><i class="fas fa-plus me-2"></i>Tambah Peminjaman</h1></div>

<form action="{{ route('borrowings.store') }}" method="POST">
    @csrf
    <div class="card card-undira">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Inventaris <span class="text-danger">*</span></label>
                    <select name="inventory_item_id" class="form-select select2" required>
                        <option value="">Pilih Inventaris</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" {{ old('inventory_item_id', request('inventory_item_id')) == $item->id ? 'selected' : '' }}>
                                {{ $item->inventory_number }} - {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Peminjam <span class="text-danger">*</span></label>
                    <input type="text" name="borrower_name" class="form-control" value="{{ old('borrower_name') }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email Peminjam</label>
                    <input type="email" name="borrower_email" class="form-control" value="{{ old('borrower_email') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Telepon Peminjam</label>
                    <input type="text" name="borrower_phone" class="form-control" value="{{ old('borrower_phone') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal Pinjam <span class="text-danger">*</span></label>
                    <input type="date" name="borrow_date" class="form-control" value="{{ old('borrow_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal Kembali (Rencana)</label>
                    <input type="date" name="expected_return_date" class="form-control" value="{{ old('expected_return_date') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach(\App\Enums\BorrowingStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ old('status', 'dipinjam') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Simpan</button>
            <a href="{{ route('borrowings.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>$('.select2').select2({ theme: 'bootstrap-5', width: '100%' });</script>
@endpush
