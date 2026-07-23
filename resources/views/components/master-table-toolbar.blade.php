@props([
    'action',
    'placeholder' => 'Cari…',
    'showActiveFilter' => true,
    'sortOptions' => ['name' => 'Nama'],
    'exportRoute' => null,
    'importRoute' => null,
    'templateRoute' => null,
    'hidden' => [],
])

@php
    $currentSort = request('sort', array_key_first($sortOptions) ?: 'name');
    $currentDir = request('dir', 'asc') === 'desc' ? 'desc' : 'asc';
    $resetUrl = $hidden === []
        ? $action
        : $action.(str_contains((string) $action, '?') ? '&' : '?').http_build_query($hidden);
@endphp

<div class="card card-undira mb-3 master-table-toolbar">
    <div class="card-body py-2">
        <form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
            @foreach($hidden as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <div class="col-md-4 col-lg-3">
                <label class="form-label small mb-0" for="masterSearchQ">Cari</label>
                <input type="text" name="q" id="masterSearchQ" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ $placeholder }}">
            </div>

            @if($showActiveFilter)
            <div class="col-md-3 col-lg-2">
                <label class="form-label small mb-0" for="masterFilterActive">Status</label>
                <select name="is_active" id="masterFilterActive" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="1" @selected(request('is_active') === '1')>Aktif</option>
                    <option value="0" @selected(request('is_active') === '0')>Nonaktif</option>
                </select>
            </div>
            @endif

            <div class="col-md-3 col-lg-2">
                <label class="form-label small mb-0" for="masterSort">Urutkan</label>
                <select name="sort" id="masterSort" class="form-select form-select-sm">
                    @foreach($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 col-lg-1">
                <label class="form-label small mb-0" for="masterDir">Arah</label>
                <select name="dir" id="masterDir" class="form-select form-select-sm">
                    <option value="asc" @selected($currentDir === 'asc')>A–Z</option>
                    <option value="desc" @selected($currentDir === 'desc')>Z–A</option>
                </select>
            </div>

            <div class="col-md-12 col-lg-auto d-flex flex-wrap gap-1">
                <button type="submit" class="btn btn-sm btn-outline-primary" data-no-loading>
                    <i class="fas fa-search me-1"></i>Terapkan
                </button>
                <a href="{{ $resetUrl }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        @if($exportRoute || $importRoute || $templateRoute)
        <div class="d-flex flex-wrap gap-1 mt-2 pt-2 border-top">
            @if($exportRoute)
            <a href="{{ $exportRoute }}" class="btn btn-sm btn-outline-success">
                <i class="fas fa-file-export me-1"></i>Export CSV
            </a>
            @endif
            @if($templateRoute)
            <a href="{{ $templateRoute }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-download me-1"></i>Template
            </a>
            @endif
            @if($importRoute)
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalMasterImport" data-no-loader="1">
                <i class="fas fa-file-import me-1"></i>Import
            </button>
            @endif
        </div>
        @endif
    </div>
</div>

@if($importRoute)
@once
@push('modals')
<div class="modal fade" id="modalMasterImport" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ $importRoute }}" method="POST" enctype="multipart/form-data" class="modal-content" data-no-loading>
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Import Master Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Unggah file CSV sesuai template. Baris dengan kode/nama yang sama akan diperbarui bila cocok.</p>
                <div class="mb-0">
                    <label class="form-label">Berkas CSV / Excel</label>
                    <input type="file" name="file" class="form-control" accept=".csv,.xlsx,.xls" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira" data-loading-text="Mengimpor...">Import</button>
            </div>
        </form>
    </div>
</div>
@endpush
@endonce
@endif
