@extends('layouts.app')

@section('title', $campus->name . ' - Inventaris UNDIRA')

@push('styles')
<style>
.inventory-nested-tree .inv-node {
    border: 1px solid #e2e8f0;
    border-radius: .5rem;
    background: #fff;
    margin-bottom: .45rem;
    overflow: hidden;
}
.inventory-nested-tree .inv-node--floor { margin-left: .65rem; }
.inventory-nested-tree .inv-node--room { margin-left: .9rem; }
.inventory-nested-tree .inv-node--category { margin-left: 1.15rem; }
.inventory-nested-tree .inv-node--subcategory { margin-left: 1.4rem; }
@media (min-width: 768px) {
    .inventory-nested-tree .inv-node--floor { margin-left: 1rem; }
    .inventory-nested-tree .inv-node--room { margin-left: 1.35rem; }
    .inventory-nested-tree .inv-node--category { margin-left: 1.7rem; }
    .inventory-nested-tree .inv-node--subcategory { margin-left: 2.05rem; }
}
.inventory-nested-tree .inv-node__header {
    display: flex;
    align-items: center;
    gap: .75rem;
    width: 100%;
    padding: .7rem 1rem;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
}
.inventory-nested-tree .inv-node--building > .inv-node__header {
    background: linear-gradient(90deg, rgba(30, 58, 95, .07), transparent);
    font-weight: 700;
}
.inventory-nested-tree .inv-node--floor > .inv-node__header {
    background: rgba(46, 125, 50, .05);
    font-weight: 600;
}
.inventory-nested-tree .inv-node--room > .inv-node__header {
    background: rgba(15, 118, 110, .05);
}
.inventory-nested-tree .inv-node--category > .inv-node__header {
    background: rgba(124, 58, 237, .05);
}
.inventory-nested-tree .inv-node--subcategory > .inv-node__header {
    background: rgba(217, 119, 6, .05);
}
.inventory-nested-tree .inv-node__title { flex: 1 1 auto; min-width: 0; }
.inventory-nested-tree .inv-node__chevron {
    flex: 0 0 auto;
    width: 1.25rem;
    text-align: center;
    color: #64748b;
    font-size: .85rem;
}
.inventory-nested-tree .inv-node.is-open > .inv-node__header .inv-node__chevron::before { content: "▼"; }
.inventory-nested-tree .inv-node:not(.is-open) > .inv-node__header .inv-node__chevron::before { content: "▶"; }
.inventory-nested-tree .inv-node__body { display: none; padding: .2rem .7rem .7rem; }
.inventory-nested-tree .inv-node.is-open > .inv-node__body { display: block; }
.inventory-nested-tree .inv-badge {
    flex: 0 0 auto;
    background: #1e3a5f;
    color: #fff;
    border-radius: 999px;
    padding: .2rem .65rem;
    font-size: .75rem;
    font-weight: 600;
    white-space: nowrap;
}
.inventory-nested-tree .inv-node--floor .inv-badge { background: #2E7D32; }
.inventory-nested-tree .inv-node--room .inv-badge { background: #0f766e; }
.inventory-nested-tree .inv-node--category .inv-badge { background: #7c3aed; }
.inventory-nested-tree .inv-node--subcategory .inv-badge { background: #d97706; }
.inventory-nested-tree .inv-loading,
.inventory-nested-tree .inv-empty {
    padding: .75rem 1rem;
    color: #64748b;
    font-size: .875rem;
}
.inventory-nested-tree .inv-items-wrap {
    border: 1px solid #e2e8f0;
    border-radius: .4rem;
    overflow: hidden;
    background: #fff;
}
[data-theme="dark"] .inventory-nested-tree .inv-node,
body.dark-mode .inventory-nested-tree .inv-node,
[data-theme="dark"] .inventory-nested-tree .inv-items-wrap,
body.dark-mode .inventory-nested-tree .inv-items-wrap {
    background: #111827;
    border-color: #334155;
}
</style>
@endpush

@section('content')
<x-page-header
    :title="$campus->name"
    subtitle="Gedung → Lantai → Ruangan → Kategori → Sub Kategori → Inventaris"
    icon="bi bi-building"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Data Inventaris', 'url' => route('inventory.index')],
        ['label' => $campus->name],
    ]"
    :back-route="route('inventory.index')"
    back-label="Data Inventaris"
>
    <button type="button" class="btn btn-outline-primary btn-sm" id="btnExpandAll" data-no-loader="1">
        <i class="bi bi-arrows-fullscreen me-1"></i>Expand Semua
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCollapseAll" data-no-loader="1">
        <i class="bi bi-fullscreen-exit me-1"></i>Collapse Semua
    </button>
    @can('inventory.create')
    <a href="{{ route('categories.index', ['tab' => 'konfigurasi', 'create' => 1]) }}" class="btn btn-undira btn-sm"><i class="bi bi-sliders me-1"></i>Konfigurasi Inventaris</a>
    @endcan
</x-page-header>

<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-3"><div class="card card-undira card-stat"><div class="card-body py-3"><div class="text-muted small">Total Unit</div><div class="fs-4 fw-bold">{{ number_format($summary['total_units']) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-undira card-stat stat-blue"><div class="card-body py-3"><div class="text-muted small">Grup Item</div><div class="fs-4 fw-bold">{{ number_format($summary['total_groups']) }}</div></div></div></div>
    <div class="col-4 col-md-2"><div class="card card-undira"><div class="card-body py-3"><div class="text-muted small">Gedung</div><div class="fs-5 fw-bold">{{ $summary['buildings'] }}</div></div></div></div>
    <div class="col-4 col-md-2"><div class="card card-undira"><div class="card-body py-3"><div class="text-muted small">Lantai</div><div class="fs-5 fw-bold">{{ $summary['floors'] }}</div></div></div></div>
    <div class="col-4 col-md-2"><div class="card card-undira"><div class="card-body py-3"><div class="text-muted small">Ruangan</div><div class="fs-5 fw-bold">{{ $summary['rooms'] }}</div></div></div></div>
</div>

<div class="filter-panel">
    <form method="GET" action="{{ route('inventory.campus', $campus) }}" id="filterForm">
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <label class="form-label small">Cari</label>
                <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Nama / kode / no. inventaris...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Gedung</label>
                <select name="building_id" id="filterBuilding" class="form-select"><option value="">Semua</option></select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Lantai</label>
                <select name="floor_id" id="filterFloor" class="form-select"><option value="">Semua</option></select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Ruangan</label>
                <select name="room_id" id="filterRoom" class="form-select"><option value="">Semua</option></select>
            </div>
            <div class="col-6 col-md-2 d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-undira btn-sm flex-grow-1"><i class="fas fa-filter"></i></button>
                <a href="{{ route('inventory.campus', $campus) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i></a>
            </div>
        </div>
    </form>
</div>

@if($buildings->isEmpty())
<div class="alert alert-light border text-center">Belum ada inventaris di kampus ini{{ request()->hasAny(['search','building_id','floor_id','room_id']) ? ' dengan filter aktif' : '' }}.</div>
@else
<div class="inventory-nested-tree" id="inventoryNestedTree"
     data-campus-id="{{ $campus->id }}"
     data-floors-base="{{ $treeUrls['floors'] }}"
     data-rooms-base="{{ $treeUrls['rooms'] }}"
     data-categories-base="{{ $treeUrls['categories'] }}"
     data-query="{{ http_build_query(array_filter($filters ?? [])) }}">
    @foreach($buildings as $building)
    <div class="inv-node inv-node--building" data-level="building" data-id="{{ $building['id'] }}" data-state-key="b:{{ $building['id'] }}">
        <button type="button" class="inv-node__header" data-no-loader="1" aria-expanded="false">
            <span class="inv-node__chevron" aria-hidden="true"></span>
            <span class="inv-node__title">
                <i class="fas fa-building me-2 text-undira-blue"></i>{{ $building['name'] }}
                @if($building['code']) <code class="small text-muted">{{ $building['code'] }}</code> @endif
            </span>
            <span class="inv-badge">{{ number_format($building['unit_count']) }} Unit</span>
        </button>
        <div class="inv-node__body" hidden></div>
    </div>
    @endforeach
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const locationData = @json($locationData);
    const selected = {
        building: @json($filters['building_id'] ?? null),
        floor: @json($filters['floor_id'] ?? null),
        room: @json($filters['room_id'] ?? null),
    };

    function populate($el, items, placeholder, selectedId) {
        $el.empty().append(`<option value="">${placeholder}</option>`);
        (items || []).forEach(item => {
            $el.append(`<option value="${item.id}" ${String(item.id) === String(selectedId || '') ? 'selected' : ''}>${item.name}</option>`);
        });
    }
    function getCampus() { return locationData[0] || null; }
    function getBuilding() {
        return getCampus()?.buildings?.find(b => String(b.id) === String($('#filterBuilding').val())) || null;
    }
    function getFloor() {
        return getBuilding()?.floors?.find(f => String(f.id) === String($('#filterFloor').val())) || null;
    }
    function initFilters() {
        populate($('#filterBuilding'), getCampus()?.buildings || [], 'Semua', selected.building);
        populate($('#filterFloor'), getBuilding()?.floors || [], 'Semua', selected.floor);
        populate($('#filterRoom'), getFloor()?.rooms || [], 'Semua', selected.room);
    }
    $('#filterBuilding').on('change', function () {
        selected.building = $(this).val() || null;
        selected.floor = selected.room = null;
        populate($('#filterFloor'), getBuilding()?.floors || [], 'Semua', null);
        populate($('#filterRoom'), [], 'Semua', null);
    });
    $('#filterFloor').on('change', function () {
        selected.floor = $(this).val() || null;
        selected.room = null;
        populate($('#filterRoom'), getFloor()?.rooms || [], 'Semua', null);
    });
    initFilters();

    const $tree = $('#inventoryNestedTree');
    if (!$tree.length) return;

    const campusId = String($tree.data('campus-id'));
    const storageKey = 'inventory.campus.accordion.v2.' + campusId;
    const floorsBase = $tree.data('floors-base');
    const roomsBase = $tree.data('rooms-base');
    const categoriesBase = $tree.data('categories-base');
    const query = String($tree.data('query') || '');
    const ANIM_MS = 200;

    function loadState() {
        try { return JSON.parse(localStorage.getItem(storageKey) || '{}') || {}; }
        catch (e) { return {}; }
    }
    function saveState(state) { localStorage.setItem(storageKey, JSON.stringify(state)); }
    function stateKey($node) { return String($node.data('state-key') || $node.data('id')); }
    function markOpen($node, open) {
        const state = loadState();
        const key = stateKey($node);
        if (open) state[key] = 1;
        else delete state[key];
        saveState(state);
    }
    function withQuery(url) {
        if (!query) return url;
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + query;
    }
    function esc(text) { return $('<div>').text(text == null ? '' : String(text)).html(); }
    function badge(count) {
        return '<span class="inv-badge">' + Number(count).toLocaleString('id-ID') + ' Unit</span>';
    }
    function nodeHtml(opts) {
        return (
            '<div class="inv-node inv-node--' + opts.cls + '" data-level="' + opts.level + '" data-id="' + opts.id + '" data-state-key="' + esc(opts.stateKey) + '"'
            + (opts.roomId ? ' data-room-id="' + opts.roomId + '"' : '')
            + (opts.categoryId != null ? ' data-category-id="' + opts.categoryId + '"' : '')
            + '>'
            + '<button type="button" class="inv-node__header" data-no-loader="1" aria-expanded="false">'
            + '<span class="inv-node__chevron" aria-hidden="true"></span>'
            + '<span class="inv-node__title"><i class="' + opts.icon + ' me-2"></i>' + esc(opts.label)
            + (opts.code ? ' <code class="small text-muted">' + esc(opts.code) + '</code>' : '')
            + '</span>' + badge(opts.count)
            + '</button><div class="inv-node__body" hidden></div></div>'
        );
    }

    function slideOpen($body, done) {
        $body.stop(true, true).css({ display: 'block', overflow: 'hidden', height: 0, opacity: 0 });
        const target = $body.get(0).scrollHeight;
        $body.animate({ height: target, opacity: 1 }, ANIM_MS, function () {
            $body.css({ height: '', overflow: '', opacity: '' });
            if (done) done();
        });
    }
    function slideClose($body, done) {
        $body.stop(true, true).css({ overflow: 'hidden', height: $body.get(0).scrollHeight, opacity: 1 });
        $body.animate({ height: 0, opacity: 0 }, ANIM_MS, function () {
            $body.hide().css({ height: '', overflow: '', opacity: '', display: '' }).attr('hidden', true);
            if (done) done();
        });
    }

    function renderFloors(floors, buildingId) {
        if (!floors.length) return '<div class="inv-empty">Tidak ada lantai dengan inventaris.</div>';
        return floors.map(f => nodeHtml({
            cls: 'floor', level: 'floor', id: f.id, stateKey: 'f:' + f.id,
            icon: 'fas fa-layer-group text-success', label: f.name, code: f.code, count: f.unit_count,
        })).join('');
    }
    function renderRooms(rooms) {
        if (!rooms.length) return '<div class="inv-empty">Tidak ada ruangan dengan inventaris.</div>';
        return rooms.map(r => nodeHtml({
            cls: 'room', level: 'room', id: r.id, stateKey: 'r:' + r.id,
            icon: 'fas fa-door-open text-teal', label: r.name + (r.code ? ' (' + r.code + ')' : ''), count: r.unit_count,
        })).join('');
    }
    function renderCategories(cats, roomId) {
        if (!cats.length) return '<div class="inv-empty">Tidak ada kategori di ruangan ini.</div>';
        return cats.map(c => nodeHtml({
            cls: 'category', level: 'category', id: c.id, stateKey: 'c:' + roomId + ':' + c.id,
            roomId: roomId, icon: 'fas fa-tags text-purple', label: c.name, code: c.code, count: c.unit_count,
        })).join('');
    }
    function renderSubCategories(subs, roomId, categoryId) {
        if (!subs.length) return '<div class="inv-empty">Tidak ada sub kategori.</div>';
        return subs.map(s => nodeHtml({
            cls: 'subcategory', level: 'subcategory', id: s.id,
            stateKey: 's:' + roomId + ':' + categoryId + ':' + s.id,
            roomId: roomId, categoryId: categoryId,
            icon: 'fas fa-tag text-warning', label: s.name, code: s.code, count: s.unit_count,
        })).join('');
    }

    function fetchChildren($node) {
        const level = $node.data('level');
        const id = $node.data('id');
        const $body = $node.children('.inv-node__body');
        let url = null;

        if (level === 'building') {
            url = withQuery(floorsBase + '/' + id + '/floors');
        } else if (level === 'floor') {
            url = withQuery(roomsBase + '/' + id + '/rooms');
        } else if (level === 'room') {
            url = withQuery(categoriesBase + '/' + id + '/categories');
        } else if (level === 'category') {
            const roomId = $node.data('room-id');
            url = withQuery(categoriesBase + '/' + roomId + '/categories/' + id + '/sub-categories');
        } else if (level === 'subcategory') {
            const roomId = $node.data('room-id');
            const categoryId = $node.data('category-id');
            url = withQuery(categoriesBase + '/' + roomId + '/categories/' + categoryId + '/sub-categories/' + id + '/items');
        }
        if (!url) return $.Deferred().reject().promise();

        $body.html('<div class="inv-loading"><i class="fas fa-spinner fa-spin me-1"></i>Memuat…</div>');

        return $.getJSON(url).then(function (res) {
            if (level === 'building') $body.html(renderFloors(res.nodes || [], id));
            else if (level === 'floor') $body.html(renderRooms(res.nodes || []));
            else if (level === 'room') $body.html(renderCategories(res.nodes || [], id));
            else if (level === 'category') $body.html(renderSubCategories(res.nodes || [], $node.data('room-id'), id));
            else $body.html('<div class="inv-items-wrap">' + (res.html || '<div class="inv-empty">Tidak ada inventaris.</div>') + '</div>');
            $node.data('loaded', 1);
        }).fail(function () {
            $body.html('<div class="inv-empty text-danger">Gagal memuat data.</div>');
        });
    }

    function openNode($node, opts) {
        opts = opts || {};
        if ($node.hasClass('is-open')) return $.Deferred().resolve().promise();
        const $body = $node.children('.inv-node__body');
        const proceed = function () {
            $body.removeAttr('hidden');
            $node.addClass('is-open');
            $node.children('.inv-node__header').attr('aria-expanded', 'true');
            markOpen($node, true);
            if (opts.silent) {
                $body.show();
                return $.Deferred().resolve().promise();
            }
            const d = $.Deferred();
            slideOpen($body, function () { d.resolve(); });
            return d.promise();
        };
        if ($node.data('loaded') == 1) return proceed();
        return fetchChildren($node).then(proceed);
    }

    function closeNode($node, opts) {
        opts = opts || {};
        if (!$node.hasClass('is-open')) return $.Deferred().resolve().promise();
        const $body = $node.children('.inv-node__body');
        markOpen($node, false);
        $node.removeClass('is-open');
        $node.children('.inv-node__header').attr('aria-expanded', 'false');
        if (opts.silent) {
            $body.hide().attr('hidden', true);
            return $.Deferred().resolve().promise();
        }
        const d = $.Deferred();
        slideClose($body, function () { d.resolve(); });
        return d.promise();
    }

    $tree.on('click', '.inv-node__header', function (e) {
        e.preventDefault();
        const $node = $(this).closest('.inv-node');
        if ($node.hasClass('is-open')) closeNode($node);
        else openNode($node);
    });

    async function openByStateKey(key) {
        const $node = $tree.find('.inv-node[data-state-key="' + key.replace(/"/g, '\\"') + '"]');
        if ($node.length) await openNode($node, { silent: true });
    }

    async function restoreState() {
        const state = loadState();
        const keys = Object.keys(state);
        // Open shallower levels first so children exist in DOM.
        const rank = function (key) {
            if (key.indexOf('b:') === 0) return 1;
            if (key.indexOf('f:') === 0) return 2;
            if (key.indexOf('r:') === 0) return 3;
            if (key.indexOf('c:') === 0) return 4;
            if (key.indexOf('s:') === 0) return 5;
            return 9;
        };
        keys.sort((a, b) => rank(a) - rank(b));
        for (const key of keys) {
            await openByStateKey(key);
        }
    }

    async function expandDeep($root) {
        await openNode($root, { silent: true });
        const $children = $root.children('.inv-node__body').children('.inv-node');
        for (let i = 0; i < $children.length; i++) {
            await expandDeep($children.eq(i));
        }
    }

    $('#btnCollapseAll').on('click', function () {
        $tree.find('.inv-node.is-open').each(function () {
            closeNode($(this), { silent: true });
        });
        saveState({});
    });

    $('#btnExpandAll').on('click', async function () {
        const $btn = $(this).prop('disabled', true);
        try {
            const $buildings = $tree.children('.inv-node--building');
            for (let i = 0; i < $buildings.length; i++) {
                await expandDeep($buildings.eq(i));
            }
        } finally {
            $btn.prop('disabled', false);
        }
    });

    restoreState();
})();
</script>
@endpush
