@extends('layouts.app')

@section('title', 'Tambah Peminjaman - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Pinjam Inventaris"
    subtitle="Isi data peminjam, lalu tambahkan satu atau lebih barang ke dalam satu transaksi peminjaman."
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

<form action="{{ route('borrowings.store') }}" method="POST" id="borrowForm" data-no-loading enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="status" value="menunggu_persetujuan">
    <div id="borrowCartInputs"></div>

    <div class="card card-undira mb-3">
        <div class="card-header">
            <strong>Bagian 1 — Data peminjam</strong>
            <div class="small text-muted fw-normal mt-1">Identitas orang yang membawa barang.</div>
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
                    <label class="form-label">Telepon / No. HP <span class="text-danger">*</span></label>
                    <input type="text" name="borrower_phone" class="form-control @error('borrower_phone') is-invalid @enderror" value="{{ old('borrower_phone') }}" autocomplete="tel" required>
                    @error('borrower_phone')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label" for="borrow_date">Tanggal &amp; Jam Pinjam <span class="text-danger">*</span></label>
                    <input type="datetime-local" step="60" id="borrow_date" name="borrow_date" class="form-control @error('borrow_date') is-invalid @enderror" value="{{ old('borrow_date', now()->format('Y-m-d\TH:i')) }}" required>
                    @error('borrow_date')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label" for="expected_return_date">Tanggal &amp; Jam Kembali <span class="text-danger">*</span></label>
                    <input type="datetime-local" step="60" id="expected_return_date" name="expected_return_date" class="form-control @error('expected_return_date') is-invalid @enderror" value="{{ old('expected_return_date') }}" required>
                    @error('expected_return_date')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mb-0">
                    <label class="form-label">Keperluan <span class="text-danger">*</span></label>
                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2" placeholder="Contoh: kegiatan akademik, rapat, praktikum, acara kampus…" required>{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mt-3 mb-0">
                    <label class="form-label" for="supporting_document">Dokumen Pendukung</label>
                    <input
                        type="file"
                        name="supporting_document"
                        id="supporting_document"
                        class="form-control @error('supporting_document') is-invalid @enderror"
                        accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg"
                    >
                    <div class="form-text">Opsional. Format PDF, JPG, atau JPEG, maksimal 5 MB.</div>
                    <div id="supportingDocumentInfo" class="small mt-2 d-none"></div>
                    <div id="supportingDocumentClientError" class="invalid-feedback d-block d-none"></div>
                    @error('supporting_document')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2 d-none" id="supportingDocumentClear">Hapus</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-undira mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Bagian 2 — Daftar barang</strong>
                <div class="small text-muted fw-normal mt-1">Satu transaksi dapat berisi banyak inventaris dari berbagai kategori / lokasi.</div>
            </div>
            <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="collapse" data-bs-target="#addItemPanel">
                <i class="fas fa-plus me-1"></i>Tambah Barang
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:48px">No</th>
                            <th>Nomor Inventaris</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Sub Kategori</th>
                            <th style="width:90px">Jumlah</th>
                            <th style="width:80px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="borrowCartBody">
                        <tr id="borrowCartEmpty">
                            <td colspan="7" class="text-center text-muted py-4">Belum ada barang. Klik <strong>Tambah Barang</strong> untuk memilih inventaris.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-undira" id="btnBorrowSubmit" disabled>
                <i class="fas fa-save me-1"></i>Simpan Peminjaman
            </button>
            <a href="{{ route('borrowings.index') }}" class="btn btn-outline-secondary">Batal</a>
            <span class="small text-muted" id="borrowSubmitHint">Tombol Simpan terkunci sampai minimal satu barang ditambahkan.</span>
        </div>
    </div>

    <div class="collapse mb-3 @if(!empty($selectedDetail)) show @endif" id="addItemPanel">
        <div class="card card-undira">
            <div class="card-header">
                <strong>Pilih barang</strong>
                <div class="small text-muted fw-normal mt-1">
                    Kampus → Gedung → Lantai → Ruangan → Kategori → Sub Kategori → Barang.
                    Setelah dipilih, masukkan ke daftar — belum membuat transaksi.
                </div>
            </div>
            <div class="card-body">
                <x-inventory-selector
                    name="picker_inventory_item_id"
                    id="borrow-inventory-selector"
                    :selected-id="$selectedDetail['id'] ?? null"
                    :selected-label="isset($selectedDetail) ? ($selectedDetail['inventory_number'].' — '.$selectedDetail['name']) : null"
                    status-filter="aktif"
                    require-borrowable
                    show-qr-scan
                    modes="both"
                    :required="false"
                    help-text="Ketik minimal 2 huruf/angka: nomor inventaris, nama, barcode, atau serial. Bisa juga scan QR."
                    label="Barang yang dipinjam"
                />
                <div class="row align-items-end mt-3">
                    <div class="col-md-3 mb-2">
                        <label class="form-label" for="addItemQty">Jumlah</label>
                        <input type="number" id="addItemQty" class="form-control" min="1" max="500" value="1">
                        <div class="form-text" id="addItemQtyHint">Tersedia: —</div>
                    </div>
                    <div class="col-md-9 mb-2 d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-undira" id="btnAddToCart" disabled>
                            <i class="fas fa-cart-plus me-1"></i>Masukkan ke Daftar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#addItemPanel">Tutup</button>
                    </div>
                </div>
                <div id="addItemAlert" class="alert alert-warning mt-2 mb-0 d-none" role="alert"></div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    var cart = [];
    var selected = null;
    var siblingsUrl = @json(url('/inventory/selector/items'));
    var $body = $('#borrowCartBody');
    var $empty = $('#borrowCartEmpty');
    var $inputs = $('#borrowCartInputs');
    var $submit = $('#btnBorrowSubmit');
    var $hint = $('#borrowSubmitHint');
    var $addBtn = $('#btnAddToCart');
    var $qty = $('#addItemQty');
    var $qtyHint = $('#addItemQtyHint');
    var $alert = $('#addItemAlert');
    var oldIds = @json(array_values(array_filter(array_map('intval', (array) old('inventory_item_ids', old('inventory_item_id') ? [old('inventory_item_id')] : [])))));
    var bootstrapDetail = @json($selectedDetail);

    function alertMsg(msg) {
        $alert.toggleClass('d-none', !msg).text(msg || '');
    }

    function ids() {
        return cart.map(function (r) { return r.id; });
    }

    function render() {
        $body.find('tr.cart-row').remove();
        $inputs.empty();
        if (!cart.length) {
            $empty.removeClass('d-none');
            $submit.prop('disabled', true);
            $hint.text('Tombol Simpan terkunci sampai minimal satu barang ditambahkan.');
            return;
        }
        $empty.addClass('d-none');
        cart.forEach(function (row, i) {
            $inputs.append($('<input>', { type: 'hidden', name: 'inventory_item_ids[]', value: row.id }));
            var $tr = $('<tr class="cart-row">').attr('data-id', row.id);
            $tr.append($('<td>').text(i + 1));
            $tr.append($('<td>').html('<code></code>').find('code').text(row.inventory_number).end());
            $tr.append($('<td>').text(row.name));
            $tr.append($('<td>').text(row.category || '—'));
            $tr.append($('<td>').text(row.sub_category || '—'));
            $tr.append($('<td>').text('1'));
            $tr.append($('<td>').html('<button type="button" class="btn btn-sm btn-outline-danger btn-remove-cart"><i class="fas fa-trash"></i></button>'));
            $body.append($tr);
        });
        $submit.prop('disabled', false);
        $hint.text('Siap disimpan: ' + cart.length + ' unit inventaris dalam satu transaksi.');
    }

    function pushItem(d) {
        if (!d || !d.id || !d.borrowable) return false;
        if (ids().indexOf(d.id) !== -1) return false;
        cart.push({
            id: d.id,
            inventory_number: d.inventory_number,
            name: d.name,
            category: d.category,
            sub_category: d.sub_category
        });
        return true;
    }

    $('#borrow-inventory-selector').on('inventory-selector:selected', function (_e, detail, meta) {
        var ready = !!(detail && detail.borrowable && !(meta && meta.blocked));
        selected = ready ? detail : null;
        $addBtn.prop('disabled', !ready);
        var avail = ready ? (detail.available_qty || 1) : 0;
        $qty.attr('max', Math.max(1, avail)).val(1);
        $qtyHint.text(ready ? ('Tersedia: ' + avail + ' unit') : 'Tersedia: —');
        if (!ready && detail) {
            alertMsg(detail.active_borrowing
                ? 'Barang sedang dipinjam atau menunggu persetujuan.'
                : ('Tidak dapat dipinjam (status: ' + (detail.status_label || detail.status) + ').'));
        } else {
            alertMsg('');
        }
    });

    $addBtn.on('click', function () {
        if (!selected) return;
        var qty = Math.max(1, parseInt($qty.val(), 10) || 1);
        var avail = selected.available_qty || 1;
        if (qty > avail) {
            alertMsg('Jumlah melebihi stok tersedia (' + avail + ').');
            return;
        }

        $addBtn.prop('disabled', true);
        var exclude = ids().join(',');
        var url = siblingsUrl + '/' + selected.id + '/borrow-siblings?qty=' + qty + '&exclude=' + encodeURIComponent(exclude);

        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok) {
                    var msg = (result.data && result.data.message) || (result.data && result.data.items && result.data.items[0]) || 'Gagal menambahkan barang.';
                    if (typeof msg !== 'string') msg = 'Gagal menambahkan barang.';
                    alertMsg(msg);
                    return;
                }
                var items = result.data.items || [];
                var added = 0;
                items.forEach(function (d) { if (pushItem(d)) added++; });
                render();
                if (!added) {
                    alertMsg('Barang sudah ada di daftar atau tidak tersedia.');
                } else {
                    alertMsg('');
                }
            })
            .catch(function () {
                // Fallback: add seed only
                if (qty === 1 && pushItem(selected)) {
                    render();
                    alertMsg('');
                } else {
                    alertMsg('Gagal mengambil unit tersedia. Coba tambahkan satu per satu.');
                }
            })
            .finally(function () {
                $addBtn.prop('disabled', !selected);
            });
    });

    $body.on('click', '.btn-remove-cart', function () {
        var id = parseInt($(this).closest('tr').data('id'), 10);
        cart = cart.filter(function (r) { return r.id !== id; });
        render();
    });

    if (oldIds.length) {
        oldIds.forEach(function (id) {
            fetch(siblingsUrl + '/' + id, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var detail = d.item || d;
                    if (detail && detail.id) {
                        detail.borrowable = true;
                        pushItem(detail);
                        render();
                    }
                });
        });
    } else if (bootstrapDetail && bootstrapDetail.borrowable) {
        selected = bootstrapDetail;
        $addBtn.prop('disabled', false);
        $qtyHint.text('Tersedia: ' + (bootstrapDetail.available_qty || 1) + ' unit');
    }

    var $docInput = $('#supporting_document');
    var $docInfo = $('#supportingDocumentInfo');
    var $docError = $('#supportingDocumentClientError');
    var $docClear = $('#supportingDocumentClear');
    var maxBytes = 5 * 1024 * 1024;

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function clearDocumentSelection() {
        revokePreview();
        $docInput.val('');
        $docInfo.addClass('d-none').empty();
        $docError.addClass('d-none').text('');
        $docClear.addClass('d-none');
        $docInput.removeClass('is-invalid');
    }

    var previewUrl = null;

    function revokePreview() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
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

    $docClear.on('click', function () {
        clearDocumentSelection();
    });

    $('#borrowForm').on('submit', function (e) {
        var file = $docInput[0].files && $docInput[0].files[0] ? $docInput[0].files[0] : null;
        if (file && !validateDocumentFile(file)) {
            e.preventDefault();
            return false;
        }
    });
})(window.jQuery);
</script>
@endpush
