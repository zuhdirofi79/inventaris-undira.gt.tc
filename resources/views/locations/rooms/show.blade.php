@extends('layouts.app')

@section('title', $room->name . ' - Inventaris UNDIRA')

@php
    $campus = $room->floor->building->campus;
    $building = $room->floor->building;
    $floor = $room->floor;
@endphp

@push('styles')
@include('locations.partials.explore-styles')
@endpush

@section('content')
<x-page-header
    :title="$room->name"
    :subtitle="($room->room_type ? $room->room_type.' · ' : '') . $room->full_location"
    icon="bi bi-door-open"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Jelajahi Lokasi', 'url' => route('campuses.index')],
        ['label' => $campus->name, 'url' => route('campuses.show', $campus)],
        ['label' => $building->name, 'url' => route('buildings.show', $building)],
        ['label' => $floor->name, 'url' => route('floors.show', $floor)],
        ['label' => $room->name],
    ]"
    :back-route="route('floors.show', $floor)"
    back-label="Ruangan"
    :list-back="true"
>
    <x-slot:actions>
        @canany(['inventory.create', 'category.manage'])
        <a href="{{ route('categories.index', [
                'tab' => 'konfigurasi',
                'create' => 1,
                'room_id' => $room->id,
                'floor_id' => $room->floor_id,
                'building_id' => $building->id,
                'campus_id' => $campus->id,
            ]) }}" class="btn btn-undira btn-sm">
            <i class="bi bi-sliders me-1"></i>Konfigurasi Inventaris
        </a>
        @endcanany
    </x-slot:actions>
</x-page-header>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card card-undira card-stat h-100">
            <div class="card-body">
                <div class="text-muted small">Total Item</div>
                <h3 class="mb-0">{{ number_format($stats['total_items']) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-undira card-stat stat-blue h-100">
            <div class="card-body">
                <div class="text-muted small">Grup Barang</div>
                <h3 class="mb-0">{{ number_format($stats['total_groups']) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-undira card-stat stat-info h-100">
            <div class="card-body">
                <div class="text-muted small">Status</div>
                @forelse($stats['by_status'] as $status => $count)
                    <small class="d-block">{{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}</small>
                @empty
                    <small class="d-block text-muted">Belum ada data</small>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
    <div>
        <h2 class="h5 mb-1"><i class="bi bi-tags me-1"></i>Isi Ruangan</h2>
        <p class="text-muted small mb-0">Buka kategori, lalu sub kategori untuk melihat daftar inventaris.</p>
    </div>
    <div class="lex-search-wrap mb-0" style="width: min(100%, 22rem);">
        <label class="form-label small mb-1" for="lexInvSearch">Cari Inventaris</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input type="search" id="lexInvSearch" class="form-control" placeholder="Nama / nomor / serial…" autocomplete="off">
        </div>
    </div>
</div>

@if($categories->isEmpty())
    <div class="lex-empty">
        <p class="fw-semibold mb-1">Belum ada inventaris di ruangan ini.</p>
        <p class="mb-2 small">Tambahkan inventaris melalui menu Konfigurasi Inventaris.</p>
        @canany(['inventory.create', 'category.manage'])
        <a href="{{ route('categories.index', [
            'tab' => 'konfigurasi',
            'create' => 1,
            'room_id' => $room->id,
            'floor_id' => $room->floor_id,
            'building_id' => $building->id,
            'campus_id' => $campus->id,
        ]) }}" class="btn btn-sm btn-undira">
            <i class="bi bi-sliders me-1"></i>Buka Konfigurasi Inventaris
        </a>
        @endcanany
    </div>
@else
    <div class="accordion lex-acc" id="lexCategoryAccordion">
        @foreach($categories as $category)
            @php $catKey = 'cat-'.$category['id']; @endphp
            <div class="accordion-item" data-category-name="{{ strtolower($category['name']) }}">
                <h3 class="accordion-header" id="heading-{{ $catKey }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapse-{{ $catKey }}" aria-expanded="false" aria-controls="collapse-{{ $catKey }}" data-no-loader="1">
                        <i class="bi bi-tag me-1 text-success" aria-hidden="true"></i>
                        <span>{{ $category['name'] }}</span>
                        @if($category['code'])
                            <small class="text-muted ms-1">({{ $category['code'] }})</small>
                        @endif
                        <span class="lex-acc__count">{{ number_format($category['unit_count']) }} Unit</span>
                    </button>
                </h3>
                <div id="collapse-{{ $catKey }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $catKey }}" data-bs-parent="#lexCategoryAccordion">
                    <div class="accordion-body">
                        <div class="accordion lex-acc lex-acc-sub" id="lexSub-{{ $catKey }}">
                            @foreach($category['sub_categories'] as $sub)
                                @php $subKey = $catKey.'-sub-'.$sub['id']; @endphp
                                <div class="accordion-item" data-sub-name="{{ strtolower($sub['name']) }}">
                                    <h4 class="accordion-header" id="heading-{{ $subKey }}">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#collapse-{{ $subKey }}" aria-expanded="false" aria-controls="collapse-{{ $subKey }}" data-no-loader="1">
                                            <i class="bi bi-folder me-1 text-secondary" aria-hidden="true"></i>
                                            <span>{{ $sub['name'] }}</span>
                                            <span class="lex-acc__count">{{ number_format($sub['unit_count']) }} Unit</span>
                                        </button>
                                    </h4>
                                    <div id="collapse-{{ $subKey }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $subKey }}"
                                        data-bs-parent="#lexSub-{{ $catKey }}">
                                        <div class="accordion-body">
                                            <div
                                                class="lex-items-host"
                                                data-category-id="{{ $category['id'] }}"
                                                data-sub-id="{{ $sub['id'] }}"
                                                data-loaded="0"
                                            >
                                                <div class="text-muted small py-2"><i class="bi bi-hourglass-split me-1"></i>Buka untuk memuat inventaris…</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const itemsBase = @json($exploreUrls['itemsBase']);
    const ANIM = 250;

    function esc(t) {
        return $('<div>').text(t == null ? '' : String(t)).html();
    }

    function renderTable(rows, meta, $host) {
        if (!rows.length) {
            $host.html('<div class="text-muted small py-2">Tidak ada inventaris.</div>');
            return;
        }
        const body = rows.map(function (r) {
            return '<tr data-search="' + esc(String(r.name + ' ' + r.inventory_number + ' ' + (r.serial_number || '')).toLowerCase()) + '">'
                + '<td>' + esc(r.name) + '</td>'
                + '<td><code class="text-break">' + esc(r.inventory_number) + '</code></td>'
                + '<td>' + esc(r.serial_number) + '</td>'
                + '<td>' + esc(r.pic) + '</td>'
                + '<td><span class="inventory-status-badge status-' + esc(r.status_tone || 'unknown') + '">' + esc(r.status_label) + '</span></td>'
                + '<td><span class="inventory-condition-badge condition-' + esc(r.condition_tone || 'unknown') + '">' + esc(r.condition_label) + '</span></td>'
                + '<td class="text-nowrap"><a href="' + r.urls.show + '" class="btn btn-sm btn-outline-primary" title="Detail"><i class="bi bi-eye"></i></a></td>'
                + '</tr>';
        }).join('');

        let pager = '';
        if (meta.last_page > 1) {
            pager = '<div class="p-2 d-flex justify-content-between align-items-center small">'
                + '<span>Halaman ' + meta.current_page + ' / ' + meta.last_page + ' · ' + meta.total + ' unit</span>'
                + '<span class="d-flex gap-1">'
                + '<button type="button" class="btn btn-sm btn-outline-secondary lex-page-btn" data-page="' + Math.max(1, meta.current_page - 1) + '" ' + (meta.current_page <= 1 ? 'disabled' : '') + ' data-no-loader="1">Prev</button>'
                + '<button type="button" class="btn btn-sm btn-outline-secondary lex-page-btn" data-page="' + Math.min(meta.last_page, meta.current_page + 1) + '" ' + (meta.current_page >= meta.last_page ? 'disabled' : '') + ' data-no-loader="1">Next</button>'
                + '</span></div>';
        }

        $host.html(
            '<div class="lex-items-wrap room-inventory-table">'
            + '<div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">'
            + '<thead><tr><th>Nama</th><th>Nomor Inventaris</th><th>Serial</th><th>PIC</th><th>Status</th><th>Kondisi</th><th>Aksi</th></tr></thead>'
            + '<tbody>' + body + '</tbody></table></div>' + pager + '</div>'
        );
        applyInvSearch();
    }

    function loadItems($host, page) {
        const categoryId = $host.data('category-id');
        const subId = $host.data('sub-id');
        const url = itemsBase + '/' + categoryId + '/sub-categories/' + subId + '/items?page=' + (page || 1);
        $host.html('<div class="text-muted small py-2"><i class="bi bi-arrow-repeat me-1"></i>Memuat inventaris…</div>');
        $.getJSON(url).done(function (res) {
            $host.data('loaded', 1);
            $host.data('page', res.meta?.current_page || 1);
            renderTable(res.rows || [], res.meta || {}, $host);
        }).fail(function () {
            $host.html('<div class="text-danger small py-2">Gagal memuat inventaris.</div>');
        });
    }

    $(document).on('shown.bs.collapse', '.lex-acc-sub .accordion-collapse', function () {
        const $host = $(this).find('.lex-items-host').first();
        if ($host.length && String($host.data('loaded')) !== '1') {
            loadItems($host, 1);
        }
    });

    $(document).on('click', '.lex-page-btn', function (e) {
        e.preventDefault();
        const $host = $(this).closest('.lex-items-host');
        loadItems($host, $(this).data('page'));
    });

    function applyInvSearch() {
        const q = ($('#lexInvSearch').val() || '').trim().toLowerCase();
        $('.lex-items-host tbody tr').each(function () {
            const hay = $(this).attr('data-search') || '';
            $(this).toggleClass('lex-hidden', q !== '' && hay.indexOf(q) === -1);
        });
        // Soft-filter category/sub visibility by name when searching globally before open
        if (!q) {
            $('#lexCategoryAccordion .accordion-item').removeClass('lex-hidden');
            return;
        }
        $('#lexCategoryAccordion > .accordion-item').each(function () {
            const catName = $(this).attr('data-category-name') || '';
            let anySub = false;
            $(this).find('.lex-acc-sub > .accordion-item').each(function () {
                const subName = $(this).attr('data-sub-name') || '';
                const match = catName.indexOf(q) !== -1 || subName.indexOf(q) !== -1;
                $(this).toggleClass('lex-hidden', !match && catName.indexOf(q) === -1);
                if (!$(this).hasClass('lex-hidden')) anySub = true;
            });
            $(this).toggleClass('lex-hidden', !anySub && catName.indexOf(q) === -1);
        });
    }

    $('#lexInvSearch').on('input', applyInvSearch);

    // Smooth feel for accordion (Bootstrap handles collapse; CSS transition ~250ms)
    document.documentElement.style.setProperty('--bs-accordion-btn-icon-transition', 'transform ' + ANIM + 'ms ease');
})();
</script>
@endpush
