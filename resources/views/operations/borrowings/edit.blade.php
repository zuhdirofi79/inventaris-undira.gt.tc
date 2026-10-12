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

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('borrowings.update', $borrowing) }}" method="POST" id="borrowEditForm" enctype="multipart/form-data">
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
                <div class="col-12">
                    <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label" for="borrow_date">Tanggal &amp; Jam Pinjam</label>
                    <input type="datetime-local" step="60" id="borrow_date" name="borrow_date" class="form-control @error('borrow_date') is-invalid @enderror" value="{{ old('borrow_date', $borrowing->plannedInputValue('borrow_date')) }}">
                    @error('borrow_date')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @unless($borrowing->hasPlannedTime('borrow_date'))
                        <div class="form-text">Jadwal lama tersimpan tanpa jam. Pilih jam dan menit sebelum menyimpan.</div>
                    @endunless
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label" for="expected_return_date">Tanggal &amp; Jam Kembali (Rencana)</label>
                    <input type="datetime-local" step="60" id="expected_return_date" name="expected_return_date" class="form-control @error('expected_return_date') is-invalid @enderror" value="{{ old('expected_return_date', $borrowing->plannedInputValue('expected_return_date')) }}">
                    @error('expected_return_date')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @unless($borrowing->hasPlannedTime('expected_return_date'))
                        <div class="form-text">Jadwal lama tersimpan tanpa jam. Pilih jam dan menit sebelum menyimpan.</div>
                    @endunless
                </div>
                    </div>
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
                <div class="col-12 mb-0">
                    <label class="form-label" for="supporting_document">Dokumen Pendukung</label>
                    @if($borrowing->hasSupportingDocument())
                        @php $docService = app(\App\Services\BorrowingSupportingDocumentService::class); @endphp
                        <div class="border rounded p-2 mb-2 small" id="existingSupportingDocument">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                @if($docService->isImage($borrowing))
                                    <img src="{{ route('borrowings.supporting-document.view', $borrowing) }}" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid var(--bs-border-color,#dee2e6)">
                                @else
                                    <span class="d-inline-flex align-items-center justify-content-center" style="width:72px;height:72px;border-radius:6px;border:1px solid var(--bs-border-color,#dee2e6)"><i class="fas fa-file-pdf fa-2x text-danger"></i></span>
                                @endif
                                <div class="min-w-0">
                                    <div class="fw-semibold text-break">{{ $borrowing->supporting_document_original_name ?: 'dokumen-pendukung.pdf' }}</div>
                                    <div class="text-muted">{{ $docService->humanSize($borrowing->supporting_document_size) }}</div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('borrowings.supporting-document.view', $borrowing) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" data-no-loader="1">Lihat</a>
                                <a href="{{ route('borrowings.supporting-document.download', $borrowing) }}" class="btn btn-sm btn-outline-secondary" data-no-loader="1">Download</a>
                            </div>
                            <div class="form-check mt-2 mb-0">
                                <input class="form-check-input" type="checkbox" name="remove_supporting_document" value="1" id="remove_supporting_document" @checked(old('remove_supporting_document'))>
                                <label class="form-check-label" for="remove_supporting_document">Hapus dokumen yang ada</label>
                            </div>
                        </div>
                    @endif
                    <input
                        type="file"
                        name="supporting_document"
                        id="supporting_document"
                        class="form-control @error('supporting_document') is-invalid @enderror"
                        accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg"
                    >
                    <div class="form-text">Opsional. Format PDF, JPG, atau JPEG, maksimal 5 MB. Mengunggah file baru akan mengganti dokumen lama.</div>
                    <div id="supportingDocumentInfo" class="small mt-2 d-none"></div>
                    <div id="supportingDocumentClientError" class="invalid-feedback d-block d-none"></div>
                    @error('supporting_document')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2 d-none" id="supportingDocumentClear">Batalkan pilihan file</button>
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

@push('scripts')
<script>
(function ($) {
    var $docInput = $('#supporting_document');
    var $docInfo = $('#supportingDocumentInfo');
    var $docError = $('#supportingDocumentClientError');
    var $docClear = $('#supportingDocumentClear');
    var $remove = $('#remove_supporting_document');
    var maxBytes = 5 * 1024 * 1024;

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    var previewUrl = null;

    function revokePreview() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
    }

    function clearDocumentSelection() {
        revokePreview();
        $docInput.val('');
        $docInfo.addClass('d-none').empty();
        $docError.addClass('d-none').text('');
        $docClear.addClass('d-none');
        $docInput.removeClass('is-invalid');
    }

    function documentKind(file) {
        var name = (file.name || '').toLowerCase();
        var type = (file.type || '').toLowerCase();
        var ext = name.indexOf('.') === -1 ? '' : name.split('.').pop();
        if (ext === 'pdf') {
            return (!type || type === 'application/pdf') ? 'pdf' : null;
        }
        if (ext === 'jpg' || ext === 'jpeg') {
            return (!type || type === 'image/jpeg' || type === 'image/jpg' || type === 'image/pjpeg') ? 'image' : null;
        }
        return null;
    }

    function validateDocumentFile(file) {
        if (!file) return true;
        if (!documentKind(file)) {
            $docError.removeClass('d-none').text('Format file harus PDF, JPG, atau JPEG.');
            $docInput.addClass('is-invalid');
            return false;
        }
        if (file.size > maxBytes) {
            $docError.removeClass('d-none').text('Ukuran file maksimal 5 MB.');
            $docInput.addClass('is-invalid');
            return false;
        }
        return true;
    }

    $docInput.on('change', function () {
        var file = this.files && this.files[0] ? this.files[0] : null;
        $docError.addClass('d-none').text('');
        $docInput.removeClass('is-invalid');
        if (!file) {
            clearDocumentSelection();
            return;
        }
        if ($remove.length) $remove.prop('checked', false);
        if (!validateDocumentFile(file)) {
            $docInfo.addClass('d-none').empty();
            $docClear.removeClass('d-none');
            this.value = '';
            return;
        }
        revokePreview();
        var kind = documentKind(file);
        var safeName = $('<div>').text(file.name).html();
        var visual = '';
        if (kind === 'image') {
            previewUrl = URL.createObjectURL(file);
            visual = '<img src="' + previewUrl + '" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid var(--bs-border-color,#dee2e6)">';
        } else {
            visual = '<span class="d-inline-flex align-items-center justify-content-center" style="width:72px;height:72px;border-radius:6px;border:1px solid var(--bs-border-color,#dee2e6)"><i class="fas fa-file-pdf fa-2x text-danger"></i></span>';
        }
        $docInfo.removeClass('d-none').html(
            '<div class="d-flex align-items-center gap-2">' + visual +
            '<div class="min-w-0"><div class="fw-semibold text-break">' + safeName + '</div>' +
            '<div class="text-muted">' + formatSize(file.size) + '</div></div></div>'
        );
        $docClear.removeClass('d-none');
    });

    $docClear.on('click', clearDocumentSelection);

    $('#borrowEditForm').on('submit', function (e) {
        var file = $docInput[0].files && $docInput[0].files[0] ? $docInput[0].files[0] : null;
        if (file && !validateDocumentFile(file)) {
            e.preventDefault();
            return false;
        }
    });
})(window.jQuery);
</script>
@endpush
