@php
    $executiveSummary = $executiveSummary ?? ['campuses' => [], 'filter_options' => ['campuses' => [], 'categories' => []]];
    $campuses = $executiveSummary['campuses'] ?? [];
    $options = $executiveSummary['filter_options'] ?? ['campuses' => [], 'categories' => []];
    $canRefresh = $canRefresh ?? false;
@endphp

<div
    id="campusExecutiveSection"
    class="campus-exec"
    data-refresh-url="{{ route('dashboard.campus-summary.refresh') }}"
>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
        <x-ui.section-title
            title="Ringkasan Inventaris per Kampus"
            icon="bi bi-diagram-3"
            subtitle="Distribusi inventaris berdasarkan kategori dan sub kategori."
            class="mb-0"
        />
        @if($canRefresh)
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefreshCampusSummary" data-no-loading>
                <i class="fas fa-sync-alt me-1"></i>Refresh Data
            </button>
        @endif
    </div>

    <div class="card card-undira mb-3 campus-exec__filters">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Search SubKategori</label>
                    <input type="search" class="form-control form-control-sm" id="execSearchSub" placeholder="Contoh: Kursi" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Kampus</label>
                    <select class="form-select form-select-sm" id="execFilterCampus">
                        <option value="">Semua Kampus</option>
                        @foreach($options['campuses'] as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Kategori</label>
                    <select class="form-select form-select-sm" id="execFilterCategory">
                        <option value="">Semua Kategori</option>
                        @foreach($options['categories'] as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Status</label>
                    <select class="form-select form-select-sm" id="execFilterStatus">
                        <option value="">Semua Status</option>
                        <option value="aktif">Aktif</option>
                        <option value="dipinjam">Dipinjam</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="rusak">Rusak</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Urutkan</label>
                    <select class="form-select form-select-sm" id="execSort">
                        <option value="total_desc">Jumlah terbanyak</option>
                        <option value="total_asc">Jumlah terkecil</option>
                        <option value="name_asc">Nama A-Z</option>
                        <option value="name_desc">Nama Z-A</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="execResetFilters">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div id="campusExecutiveGrid">
        @include('dashboard.partials.campus-executive-grid', ['executiveSummary' => $executiveSummary])
    </div>
</div>
