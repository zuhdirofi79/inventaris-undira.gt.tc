@push('scripts')
<script>
(function () {
    const previewUrl = @json(route('master-configurations.preview'));
    const optionStoreUrl = @json(route('master-data.options.store'));
    const csrf = @json(csrf_token());
    const buildingsUrl = (id) => @json(url('/master-data/campuses')) + '/' + id + '/buildings';
    const floorsUrl = (id) => @json(url('/master-data/buildings')) + '/' + id + '/floors';
    const roomsUrl = (id) => @json(url('/master-data/floors')) + '/' + id + '/rooms';
    const subsUrl = (id) => @json(url('/master-data/categories')) + '/' + id + '/subclassifications';

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

    function resetChild($el, placeholder) {
        $el.prop('disabled', true).empty().append(`<option value="">${placeholder}</option>`);
    }

    async function fetchJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('Gagal memuat data');
        return res.json();
    }

    function bindForm($root) {
        const $campus = $root.find('.cfg-campus');
        const $building = $root.find('.cfg-building');
        const $floor = $root.find('.cfg-floor');
        const $room = $root.find('.cfg-room');
        const $category = $root.find('.cfg-category');
        const $sub = $root.find('.cfg-sub-category');
        const $org = $root.find('.cfg-org-unit');

        async function loadBuildings(selected) {
            const campusId = $campus.val();
            if (!campusId) {
                resetChild($building, 'Pilih Kampus dulu');
                resetChild($floor, 'Pilih Gedung dulu');
                resetChild($room, 'Pilih Lantai dulu');
                return;
            }
            $building.prop('disabled', true).html('<option value="">Memuat…</option>');
            try {
                const json = await fetchJson(buildingsUrl(campusId));
                fillSelect($building, json.data, 'Pilih Gedung', selected);
                if (!selected) {
                    resetChild($floor, 'Pilih Gedung dulu');
                    resetChild($room, 'Pilih Lantai dulu');
                }
            } catch (e) {
                fillSelect($building, [], 'Gagal memuat gedung');
            }
        }

        async function loadFloors(selected) {
            const buildingId = $building.val();
            if (!buildingId) {
                resetChild($floor, 'Pilih Gedung dulu');
                resetChild($room, 'Pilih Lantai dulu');
                return;
            }
            $floor.prop('disabled', true).html('<option value="">Memuat…</option>');
            try {
                const json = await fetchJson(floorsUrl(buildingId));
                fillSelect($floor, json.data, 'Pilih Lantai', selected);
                if (!selected) resetChild($room, 'Pilih Lantai dulu');
            } catch (e) {
                fillSelect($floor, [], 'Gagal memuat lantai');
            }
        }

        async function loadRooms(selected) {
            const floorId = $floor.val();
            if (!floorId) {
                resetChild($room, 'Pilih Lantai dulu');
                return;
            }
            $room.prop('disabled', true).html('<option value="">Memuat…</option>');
            try {
                const json = await fetchJson(roomsUrl(floorId));
                fillSelect($room, json.data, 'Pilih Ruangan', selected, (item) => `${item.code} — ${item.name}`);
            } catch (e) {
                fillSelect($room, [], 'Gagal memuat ruangan');
            }
        }

        async function loadSubs(selected) {
            const catId = $category.val();
            if (!catId) {
                resetChild($sub, 'Pilih Klasifikasi dulu');
                return;
            }
            $sub.prop('disabled', true).html('<option value="">Memuat…</option>');
            try {
                const json = await fetchJson(subsUrl(catId));
                fillSelect($sub, json.data, 'Pilih Subklasifikasi', selected);
            } catch (e) {
                fillSelect($sub, [], 'Gagal memuat subklasifikasi');
            }
        }

        async function refreshPreview() {
            const params = new URLSearchParams({
                campus_id: $campus.val() || '',
                org_unit_id: $org.val() || '',
                room_id: $room.val() || '',
                category_id: $category.val() || '',
                sub_category_id: $sub.val() || '',
            });
            try {
                const res = await fetch(previewUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                $root.find('.cfg-location-preview').val(json.generated_location_code || '');
                $root.find('.cfg-location-error').text(json.location_error || '');
                $root.find('.cfg-sequence-preview').val(json.sequence ? json.sequence.next_padded : '');
            } catch (e) {
                $root.find('.cfg-location-error').text('Gagal memuat preview.');
            }
        }

        function resetCascade() {
            resetChild($building, 'Pilih Kampus dulu');
            resetChild($floor, 'Pilih Gedung dulu');
            resetChild($room, 'Pilih Lantai dulu');
            resetChild($sub, 'Pilih Klasifikasi dulu');
            $root.find('.cfg-location-preview').val('');
            $root.find('.cfg-sequence-preview').val('');
            $root.find('.cfg-location-error').text('');
        }

        $campus.on('change', function () { loadBuildings(null); refreshPreview(); });
        $building.on('change', function () { loadFloors(null); refreshPreview(); });
        $floor.on('change', function () { loadRooms(null); refreshPreview(); });
        $room.on('change', refreshPreview);
        $org.on('change', refreshPreview);
        $category.on('change', function () { loadSubs(null); refreshPreview(); });
        $sub.on('change', refreshPreview);

        $root.data('api', { loadBuildings, loadFloors, loadRooms, loadSubs, refreshPreview, resetCascade });
    }

    $(function () {
        $('.config-form').each(function () { bindForm($(this)); });

        $('#modalCreateConfig').on('hidden.bs.modal', function () {
            const $form = $('#formCreateConfig');
            if (!$form.length) return;
            $form[0].reset();
            const api = $form.find('.config-form').data('api');
            if (api) api.resetCascade();
        });

        $('.btn-edit-config').on('click', async function () {
            let cfg = $(this).attr('data-config');
            try { cfg = typeof cfg === 'string' ? JSON.parse(cfg) : cfg; } catch (e) { return; }
            const $form = $('#formEditConfig');
            $form.attr('action', cfg.update_url);
            const $root = $form.find('.config-form');
            const api = $root.data('api');
            $root.find('.cfg-campus').val(String(cfg.campus_id));
            $root.find('.cfg-org-unit').val(String(cfg.org_unit_id));
            $root.find('.cfg-room-function').val(cfg.room_function_id ? String(cfg.room_function_id) : '');
            $root.find('[name=procurement_type_id]').val(String(cfg.procurement_type_id));
            $root.find('.cfg-purchase-year').val(String(cfg.purchase_year_id));
            $root.find('.cfg-category').val(String(cfg.category_id));
            $root.find('.cfg-condition').val(String(cfg.inventory_condition_id));
            $root.find('[name=is_active]').prop('checked', !!cfg.is_active);
            await api.loadBuildings(cfg.building_id);
            await api.loadFloors(cfg.floor_id);
            await api.loadRooms(cfg.room_id);
            await api.loadSubs(cfg.sub_category_id);
            await api.refreshPreview();
        });

        $(document).on('click', '.btn-manage-option', function () {
            const type = $(this).data('type');
            const target = $(this).data('target');
            $('#option_type').val(type);
            $('#option_target_select').val(target);
            $('#option_error').addClass('d-none').text('');
            $('#option_code').val('');
            $('#option_name').val('');
            $('#option_year').val('');
            if (type === 'purchase_year') {
                $('#option_fields_code_name').addClass('d-none');
                $('#option_fields_year').removeClass('d-none');
            } else {
                $('#option_fields_code_name').removeClass('d-none');
                $('#option_fields_year').addClass('d-none');
            }
            new bootstrap.Modal(document.getElementById('modalManageOption')).show();
        });

        $('#formManageOption').on('submit', async function (e) {
            e.preventDefault();
            const type = $('#option_type').val();
            const target = $('#option_target_select').val();
            const body = { type, _token: csrf };
            if (type === 'purchase_year') {
                body.year = $('#option_year').val();
            } else {
                body.code = $('#option_code').val();
                body.name = $('#option_name').val();
            }
            try {
                const res = await fetch(optionStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                });
                const json = await res.json();
                if (!res.ok) {
                    const msg = json.message || Object.values(json.errors || {}).flat().join(' ') || 'Gagal menyimpan';
                    $('#option_error').removeClass('d-none').text(msg);
                    return;
                }
                const $sel = $('#' + target);
                const label = json.data.label || json.data.name || json.data.year;
                $sel.append(`<option value="${json.data.id}" selected>${label}</option>`);
                bootstrap.Modal.getInstance(document.getElementById('modalManageOption'))?.hide();
            } catch (err) {
                $('#option_error').removeClass('d-none').text('Kesalahan jaringan.');
            }
        });
    });
})();
</script>
@endpush
