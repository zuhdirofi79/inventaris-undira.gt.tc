@extends('layouts.app')

@section('title', 'Edit Peminjaman - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Edit Peminjaman"
    subtitle="Perbarui data peminjam, tanggal, dan status pengembalian"
    icon="bi bi-pencil-square"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Peminjaman', 'url' => route('borrowings.index')],
        ['label' => 'Edit'],
    ]"
    :back-route="route('borrowings.index')"
    back-label="Daftar Peminjaman"
    :list-back="true"
/>

<form action="{{ route('borrowings.update', $borrowing) }}" method="POST">
    @csrf @method('PUT')
    <div class="card card-undira">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Peminjam</label>
                    <input type="text" name="borrower_name" class="form-control" value="{{ old('borrower_name', $borrowing->borrower_name) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="borrower_email" class="form-control" value="{{ old('borrower_email', $borrowing->borrower_email) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Telepon / No. HP <span class="text-danger">*</span></label>
                    <input type="text" name="borrower_phone" class="form-control @error('borrower_phone') is-invalid @enderror" value="{{ old('borrower_phone', $borrowing->borrower_phone) }}" autocomplete="tel" required>
                    @error('borrower_phone')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach(\App\Enums\BorrowingStatus::cases() as $status)
                            <option value="{{ $status->value }}" {{ old('status', $borrowing->status?->value) == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tgl Pinjam</label>
                    <input type="date" name="borrow_date" class="form-control" value="{{ old('borrow_date', $borrowing->borrow_date?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tgl Kembali (Rencana)</label>
                    <input type="date" name="expected_return_date" class="form-control" value="{{ old('expected_return_date', $borrowing->expected_return_date?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tgl Kembali (Aktual)</label>
                    <input type="date" name="return_date" class="form-control" value="{{ old('return_date', $borrowing->return_date?->format('Y-m-d')) }}">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Keperluan <span class="text-danger">*</span></label>
                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2" placeholder="Contoh: kegiatan akademik, rapat, praktikum, acara kampus…" required>{{ old('notes', $borrowing->notes) }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Perbarui</button>
            <a href="{{ route('borrowings.show', $borrowing) }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection
