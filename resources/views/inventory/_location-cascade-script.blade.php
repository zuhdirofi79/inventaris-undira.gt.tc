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
const locationData = @json($locationData);
const subCategoryMap = @json($subCategoryMap->groupBy('category_id'));
const locSelected = {
    campus: @json($selectedCampusId),
    building: @json($selectedBuildingId),
    floor: @json($selectedFloorId),
    room: @json($selectedRoomId),
};

function populateSelect($el, items, placeholder, selectedId) {
    $el.empty().append(`<option value="">${placeholder}</option>`);
    (items || []).forEach(item => {
        const label = item.code ? `${item.name} (${item.code})` : item.name;
        $el.append(`<option value="${item.id}" ${String(item.id) === String(selectedId || '') ? 'selected' : ''}>${label}</option>`);
    });
}

function syncLocation(preserve = true) {
    const campus = locationData.find(c => String(c.id) === String($('#campus_id').val()));
    populateSelect($('#building_id'), campus?.buildings || [], 'Pilih Gedung', preserve ? locSelected.building : null);
    const building = campus?.buildings?.find(b => String(b.id) === String($('#building_id').val()));
    populateSelect($('#floor_id'), building?.floors || [], 'Pilih Lantai', preserve ? locSelected.floor : null);
    const floor = building?.floors?.find(f => String(f.id) === String($('#floor_id').val()));
    populateSelect($('#room_id'), floor?.rooms || [], 'Pilih Ruangan', preserve ? locSelected.room : null);
}

function loadSubCategories(catId, selected) {
    const $sub = $('#sub_category_id');
    $sub.empty().append('<option value="">Pilih Sub Kategori</option>');
    if (!catId || !subCategoryMap[catId]) return;
    subCategoryMap[catId].forEach(function(sub) {
        $sub.append(`<option value="${sub.id}" ${String(selected || '') === String(sub.id) ? 'selected' : ''}>${sub.name}</option>`);
    });
}

$('#campus_id').on('change', function() { locSelected.building = locSelected.floor = locSelected.room = null; syncLocation(false); });
$('#building_id').on('change', function() { locSelected.floor = locSelected.room = null; syncLocation(false); });
$('#floor_id').on('change', function() { locSelected.room = null; syncLocation(false); });
$('#category_id').on('change', function() { loadSubCategories($(this).val(), null); });

syncLocation(true);
loadSubCategories(@json($selectedCategoryId), @json($selectedSubCategoryId));
</script>
