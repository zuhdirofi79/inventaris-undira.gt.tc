@php
    $subCategoryMap = \App\Models\SubCategory::where('is_active', true)->orderBy('name')->get(['id', 'category_id', 'name']);
    $selectedCampusId = $selectedCampusId ?? null;
    $selectedBuildingId = $selectedBuildingId ?? null;
    $selectedFloorId = $selectedFloorId ?? null;
    $selectedRoomId = $selectedRoomId ?? null;
    $selectedCategoryId = $selectedCategoryId ?? null;
    $selectedSubCategoryId = $selectedSubCategoryId ?? null;
@endphp
<script>
(function () {
    const buildingsUrl = (id) => @json(url('/master-data/campuses')) + '/' + id + '/buildings';
    const floorsUrl = (id) => @json(url('/master-data/buildings')) + '/' + id + '/floors';
    const roomsUrl = (id) => @json(url('/master-data/floors')) + '/' + id + '/rooms';
    const subCategoryMap = @json($subCategoryMap->groupBy('category_id'));
    const initial = {
        campus: @json($selectedCampusId),
        building: @json($selectedBuildingId),
        floor: @json($selectedFloorId),
        room: @json($selectedRoomId),
    };

    function fillSelect($el, items, placeholder, selectedId, labelFn) {
        $el.prop('disabled', false).empty().append(`<option value="">${placeholder}</option>`);
        (items || []).forEach(item => {
            const label = labelFn ? labelFn(item) : (item.code ? `${item.name} (${item.code})` : item.name);
            $el.append(`<option value="${item.id}" ${String(item.id) === String(selectedId || '') ? 'selected' : ''}>${label}</option>`);
        });
        if (!(items || []).length) {
            $el.append('<option value="" disabled>Data tidak tersedia</option>');
        }
    }

    function resetSelect($el, placeholder) {
        $el.prop('disabled', true).empty().append(`<option value="">${placeholder}</option>`);
    }

    async function fetchJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('Gagal memuat data');
        return res.json();
    }

    async function loadBuildings(selected) {
        const campusId = $('#campus_id').val();
        if (!campusId) {
            resetSelect($('#building_id'), 'Pilih Gedung');
            resetSelect($('#floor_id'), 'Pilih Lantai');
            resetSelect($('#room_id'), 'Pilih Ruangan');
            return;
        }
        $('#building_id').prop('disabled', true).html('<option value="">Memuat…</option>');
        try {
            const json = await fetchJson(buildingsUrl(campusId));
            fillSelect($('#building_id'), json.data, 'Pilih Gedung', selected);
            if (!selected) {
                resetSelect($('#floor_id'), 'Pilih Lantai');
                resetSelect($('#room_id'), 'Pilih Ruangan');
            }
        } catch (e) {
            fillSelect($('#building_id'), [], 'Gagal memuat gedung');
        }
    }

    async function loadFloors(selected) {
        const buildingId = $('#building_id').val();
        if (!buildingId) {
            resetSelect($('#floor_id'), 'Pilih Lantai');
            resetSelect($('#room_id'), 'Pilih Ruangan');
            return;
        }
        $('#floor_id').prop('disabled', true).html('<option value="">Memuat…</option>');
        try {
            const json = await fetchJson(floorsUrl(buildingId));
            fillSelect($('#floor_id'), json.data, 'Pilih Lantai', selected);
            if (!selected) resetSelect($('#room_id'), 'Pilih Ruangan');
        } catch (e) {
            fillSelect($('#floor_id'), [], 'Gagal memuat lantai');
        }
    }

    async function loadRooms(selected) {
        const floorId = $('#floor_id').val();
        if (!floorId) {
            resetSelect($('#room_id'), 'Pilih Ruangan');
            return;
        }
        $('#room_id').prop('disabled', true).html('<option value="">Memuat…</option>');
        try {
            const json = await fetchJson(roomsUrl(floorId));
            fillSelect($('#room_id'), json.data, 'Pilih Ruangan', selected, (item) => `${item.code} — ${item.name}`);
        } catch (e) {
            fillSelect($('#room_id'), [], 'Gagal memuat ruangan');
        }
    }

    function loadSubCategories(catId, selected) {
        const $sub = $('#sub_category_id');
        $sub.empty().append('<option value="">Pilih Sub Kategori</option>');
        if (!catId || !subCategoryMap[catId]) return;
        subCategoryMap[catId].forEach(function (sub) {
            $sub.append(`<option value="${sub.id}" ${String(selected || '') === String(sub.id) ? 'selected' : ''}>${sub.name}</option>`);
        });
    }

    $('#campus_id').on('change', function () { loadBuildings(null); });
    $('#building_id').on('change', function () { loadFloors(null); });
    $('#floor_id').on('change', function () { loadRooms(null); });
    $('#category_id').on('change', function () { loadSubCategories($(this).val(), null); });

    (async function init() {
        resetSelect($('#building_id'), 'Pilih Gedung');
        resetSelect($('#floor_id'), 'Pilih Lantai');
        resetSelect($('#room_id'), 'Pilih Ruangan');
        if (initial.campus) {
            $('#campus_id').val(String(initial.campus));
            await loadBuildings(initial.building);
            if (initial.building) {
                await loadFloors(initial.floor);
                if (initial.floor) {
                    await loadRooms(initial.room);
                }
            }
        }
        loadSubCategories(@json($selectedCategoryId), @json($selectedSubCategoryId));
    })();
})();
</script>
