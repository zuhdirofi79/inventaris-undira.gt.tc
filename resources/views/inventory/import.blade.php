@extends('layouts.app')

@section('title', 'Import Inventaris - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    <li class="breadcrumb-item active">Import</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-file-import me-2"></i>Import Inventaris</h1>
    <p class="text-muted mb-0">Upload file Excel untuk mengimport data inventaris secara massal.</p>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Upload File</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <a href="{{ route('inventory.import.template') }}" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-download me-1"></i>Download Template Excel
                    </a>
                </div>
                <form id="importForm" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">File Excel (.xlsx)</label>
                        <input type="file" name="file" id="importFile" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <button type="button" id="btnPreview" class="btn btn-undira">
                        <i class="fas fa-search me-1"></i>Preview
                    </button>
                    <button type="button" id="btnProcess" class="btn btn-success" disabled>
                        <i class="fas fa-check me-1"></i>Proses Import
                    </button>
                </form>
            </div>
        </div>

        <div class="alert alert-warning small">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Pastikan data mengikuti format template. Baris dengan error akan ditampilkan di preview.
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card card-undira" id="previewCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Preview Data</h5>
                <span id="previewStats" class="badge bg-secondary">Belum ada preview</span>
            </div>
            <div class="card-body p-0">
                <div id="previewLoading" class="p-4 text-center" style="display:none;">
                    <div class="skeleton skeleton-card"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="previewTable">
                        <thead>
                            <tr>
                                <th>Baris</th>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Ruangan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="previewBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">Upload file dan klik Preview</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="errorList" class="p-3" style="display:none;">
                    <h6 class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Error</h6>
                    <ul id="errorItems" class="small text-danger mb-0"></ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let previewValid = false;

$('#btnPreview').on('click', function() {
    const file = $('#importFile')[0].files[0];
    if (!file) { Swal.fire('Error', 'Pilih file terlebih dahulu.', 'error'); return; }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', '{{ csrf_token() }}');

    const $btn = $(this);
    if (typeof setButtonLoading === 'function') setButtonLoading(this, true, 'Memproses...');

    $.ajax({
        url: '{{ route('inventory.import.preview') }}',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        undiraTarget: '#previewCard',
        success: function(res) {
            if (typeof setButtonLoading === 'function') setButtonLoading($btn[0], false);
            $('#previewStats').text(`${res.valid_rows} valid, ${res.error_rows} error`).removeClass('bg-secondary').addClass(res.error_rows > 0 ? 'bg-warning' : 'bg-success');

            let rows = '';
            (res.preview || []).forEach((row, i) => {
                rows += `<tr>
                    <td>${i + 1}</td>
                    <td>${row.name || '-'}</td>
                    <td>${row.category || row.category_name || '-'}</td>
                    <td>${row.room || row.room_code || '-'}</td>
                    <td><span class="badge bg-success">Valid</span></td>
                </tr>`;
            });
            $('#previewBody').html(rows || '<tr><td colspan="5" class="text-center text-muted">Tidak ada data valid</td></tr>');

            if (res.errors && res.errors.length > 0) {
                $('#errorList').show();
                $('#errorItems').html(res.errors.map(e => `<li>${e}</li>`).join(''));
            } else {
                $('#errorList').hide();
            }

            previewValid = res.valid_rows > 0 && res.error_rows === 0;
            $('#btnProcess').prop('disabled', !previewValid);
        },
        error: function(xhr) {
            if (typeof setButtonLoading === 'function') setButtonLoading($btn[0], false);
            Swal.fire('Error', xhr.responseJSON?.message || 'Gagal preview file.', 'error');
        }
    });
});

$('#btnProcess').on('click', function() {
    const file = $('#importFile')[0].files[0];
    if (!file || !previewValid) return;

    Swal.fire({
        title: 'Proses Import?',
        text: 'Data akan diimport ke database.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2E7D32',
        confirmButtonText: 'Ya, Import'
    }).then((result) => {
        if (!result.isConfirmed) return;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        const btn = document.getElementById('btnProcess');
        if (typeof setButtonLoading === 'function') setButtonLoading(btn, true, 'Memproses...');

        $.ajax({
            url: '{{ route('inventory.import.process') }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            undiraTarget: '#previewCard',
            success: function(res) {
                if (typeof setButtonLoading === 'function') setButtonLoading(btn, false);
                Swal.fire('Berhasil', `${res.imported} item berhasil diimport.`, 'success')
                    .then(() => window.location.href = '{{ route('inventory.index') }}');
            },
            error: function(xhr) {
                if (typeof setButtonLoading === 'function') setButtonLoading(btn, false);
                Swal.fire('Error', 'Import gagal. Periksa data Excel.', 'error');
            }
        });
    });
});
</script>
@endpush
