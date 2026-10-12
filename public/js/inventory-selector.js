/**
 * Shared InventorySelector (Select2 + location cascade + optional QR).
 * Expects jQuery + Select2 already on the page.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    function statusParams(root) {
        var status = root.getAttribute('data-status-filter') || '';
        return status ? { status: status } : {};
    }

    function fillSelect($el, results, placeholder) {
        $el.empty().append($('<option>', { value: '', text: placeholder || 'Pilih…' }));
        (results || []).forEach(function (row) {
            $el.append($('<option>', { value: row.id, text: row.text }));
        });
        $el.prop('disabled', false);
    }

    function resetDownstream($els) {
        $els.forEach(function ($el) {
            $el.empty().append($('<option>', { value: '', text: 'Pilih…' }));
            $el.prop('disabled', true).val('');
        });
    }

    function formatOption(item) {
        if (!item.id) {
            return item.text;
        }
        var num = item.inventory_number || '';
        var name = item.name || item.text || '';
        if (!num) {
            return item.text;
        }
        var $wrap = $('<div class="invsel-opt">');
        $wrap.append($('<div class="invsel-opt-name">').text(name));
        $wrap.append($('<div class="invsel-opt-num">').text('Nomor: ' + num));
        if (item.location) {
            $wrap.append($('<div class="invsel-opt-meta">').text('Lokasi: ' + item.location));
        }
        if (item.status_label) {
            $wrap.append($('<div class="invsel-opt-meta">').text('Status: ' + item.status_label));
        }
        return $wrap;
    }

    function setPreview(root, detail) {
        var card = root.querySelector('[data-invsel-preview]');
        if (!card) {
            return;
        }
        if (!detail) {
            card.classList.add('d-none');
            return;
        }
        card.classList.remove('d-none');
        card.querySelectorAll('[data-field]').forEach(function (el) {
            var key = el.getAttribute('data-field');
            el.textContent = detail[key] != null && detail[key] !== '' ? detail[key] : '—';
        });
    }

    function setBlocked(root, detail) {
        var badge = root.querySelector('[data-invsel-blocked]');
        var detailEl = root.querySelector('[data-invsel-blocked-detail]');
        var titleEl = root.querySelector('[data-invsel-blocked-title]');
        var requireBorrowable = root.getAttribute('data-require-borrowable') === '1';
        if (!badge || !requireBorrowable) {
            return { blocked: false };
        }

        var blocked = !!(detail && detail.borrowable === false);
        badge.classList.toggle('is-visible', blocked);
        if (blocked) {
            if (titleEl) {
                var statusLabel = detail.status_label || 'Tidak dapat dipinjam';
                titleEl.textContent = detail.status === 'dipinjam'
                    ? 'Sedang Dipinjam'
                    : ('Tidak dapat dipinjam (' + statusLabel + ')');
            }
            if (detailEl) {
                var ab = detail.active_borrowing || {};
                var parts = [];
                if (ab.borrower_name) {
                    parts.push('Peminjam: ' + ab.borrower_name);
                }
                if (ab.borrow_date) {
                    parts.push('Sejak: ' + ab.borrow_date);
                }
                if (ab.expected_return_date) {
                    parts.push('Rencana kembali: ' + ab.expected_return_date);
                }
                detailEl.textContent = parts.length
                    ? parts.join(' · ')
                    : ('Status saat ini: ' + (detail.status_label || 'tidak valid') + '.');
            }
        }
        return { blocked: blocked };
    }

    function setHidden(root, id) {
        var hidden = root.querySelector('[data-invsel-hidden]');
        if (hidden) {
            hidden.value = id || '';
        }
    }

    function findSubmit(root) {
        var form = root.closest('form');
        if (!form) {
            return null;
        }
        var sel = root.getAttribute('data-submit-control') || '';
        if (sel) {
            return form.querySelector(sel) || document.querySelector(sel);
        }
        return form.querySelector('[type="submit"]');
    }

    function updateSubmit(root, detail, blocked) {
        var submit = findSubmit(root);
        if (!submit) {
            return;
        }
        var requireBorrowable = root.getAttribute('data-require-borrowable') === '1';
        if (!requireBorrowable) {
            return;
        }
        // Save only when a borrowable item is selected.
        submit.disabled = !detail || blocked || detail.borrowable === false;
    }

    function applySelection(root, detail, opts) {
        opts = opts || {};
        var requireBorrowable = root.getAttribute('data-require-borrowable') === '1';
        var blockState = setBlocked(root, detail);
        var blocked = blockState.blocked;

        // Non-borrowable items may be previewed (e.g. QR scan) but must not submit.
        if (requireBorrowable && detail && detail.borrowable === false) {
            setHidden(root, '');
        } else {
            setHidden(root, detail ? detail.id : null);
        }

        setPreview(root, detail);
        updateSubmit(root, detail, blocked);

        $(root).trigger('inventory-selector:selected', [detail, {
            blocked: blocked,
            source: opts.source || 'select',
        }]);
    }

    function loadDetail(root, id, source) {
        if (!id) {
            applySelection(root, null, { source: source });
            return;
        }
        var urls = JSON.parse(root.getAttribute('data-urls') || '{}');
        var useMaint = root.getAttribute('data-resolve-maintenance') === '1';
        var url = useMaint
            ? ((urls.maintenanceContext || urls.show) + '/' + id + '/maintenance-context')
            : (urls.show + '/' + id);

        $.getJSON(url)
            .done(function (detail) {
                applySelection(root, detail, { source: source || 'select' });
            })
            .fail(function () {
                applySelection(root, null, { source: source });
            });
    }

    function bindSelect2($el, ajaxCfg) {
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
        $el.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: $el.data('placeholder') || 'Pilih…',
            allowClear: true,
            minimumInputLength: ajaxCfg.minimumInputLength || 0,
            language: {
                searching: function () { return 'Mencari inventaris…'; },
                noResults: function () { return 'Tidak ada inventaris yang cocok'; },
                errorLoading: function () { return 'Pencarian gagal. Coba lagi.'; },
                inputTooShort: function (args) {
                    return 'Ketik minimal ' + args.minimum + ' karakter';
                },
            },
            ajax: {
                url: ajaxCfg.url,
                dataType: 'json',
                delay: 250,
                data: ajaxCfg.data,
                processResults: function (data) {
                    return { results: data.results || [] };
                },
            },
            templateResult: formatOption,
            templateSelection: function (item) {
                return item.inventory_number
                    ? (item.inventory_number + ' — ' + (item.name || ''))
                    : item.text;
            },
            escapeMarkup: function (m) { return m; },
        });
    }

    function initOne(root) {
        if (root.getAttribute('data-invsel-ready') === '1') {
            return;
        }
        root.setAttribute('data-invsel-ready', '1');

        var urls = JSON.parse(root.getAttribute('data-urls') || '{}');
        var csrf = root.getAttribute('data-csrf') || '';
        var modes = root.getAttribute('data-modes') || 'both';
        var currentMode = root.getAttribute('data-default-mode') || 'location';
        var showQr = root.getAttribute('data-show-qr') === '1';

        var $campus = $(root).find('[data-invsel-campus]');
        var $building = $(root).find('[data-invsel-building]');
        var $floor = $(root).find('[data-invsel-floor]');
        var $room = $(root).find('[data-invsel-room]');
        var $category = $(root).find('[data-invsel-category]');
        var $subCategory = $(root).find('[data-invsel-subcategory]');
        var $locItem = $(root).find('[data-invsel-location-item]');
        var $quickItem = $(root).find('[data-invsel-quick-item]');
        var partial = root.getAttribute('data-partial-scope') === '1';
        var categoryToken = 0;
        var subToken = 0;

        function scopeParams() {
            return $.extend({
                campus_id: $campus.val() || '',
                building_id: $building.val() || '',
                floor_id: $floor.val() || '',
                room_id: $room.val() || '',
                category_id: $category.val() || '',
                sub_category_id: $subCategory.val() || '',
            }, statusParams(root));
        }

        function hasScope() {
            return !!($campus.val() || $building.val() || $floor.val() || $room.val() || $category.val() || $subCategory.val());
        }

        function clearLocationItem() {
            if ($locItem.length) {
                $locItem.val(null).trigger('change');
            }
            if (partial && $quickItem.length) {
                $quickItem.val(null).trigger('change');
            }
        }

        function loadScopedCategories() {
            if (!partial) {
                return;
            }
            var token = ++categoryToken;
            var params = scopeParams();
            if (!params.campus_id && !params.building_id && !params.floor_id && !params.room_id) {
                resetDownstream([$category, $subCategory]);
                return;
            }
            $.getJSON(urls.categories, params).done(function (res) {
                if (token !== categoryToken) {
                    return;
                }
                fillSelect($category, res.results, 'Semua kategori');
            });
        }

        function loadScopedSubcategories() {
            if (!partial || !$category.val()) {
                return;
            }
            var token = ++subToken;
            $.getJSON(urls.subCategories, scopeParams()).done(function (res) {
                if (token !== subToken) {
                    return;
                }
                fillSelect($subCategory, res.results, 'Semua sub kategori');
            });
        }

        function syncHiddenFromMode() {
            var id = '';
            if (currentMode === 'location' && $locItem.length) {
                id = $locItem.val() || '';
            } else if ($quickItem.length) {
                id = $quickItem.val() || '';
            }
            setHidden(root, id);
            if (id) {
                loadDetail(root, id, 'mode-sync');
            } else {
                applySelection(root, null, { source: 'clear' });
            }
        }

        function setMode(mode) {
            currentMode = mode;
            $(root).find('[data-invsel-panel]').addClass('d-none');
            $(root).find('[data-invsel-panel="' + mode + '"]').removeClass('d-none');
            $(root).find('[data-invsel-tab]').removeClass('active');
            $(root).find('[data-invsel-tab="' + mode + '"]').addClass('active');
            syncHiddenFromMode();
        }

        $(root).on('click', '[data-invsel-tab]', function () {
            setMode(this.getAttribute('data-invsel-tab'));
        });

        if ($campus.length) {
            $.getJSON(urls.campuses).done(function (res) {
                fillSelect($campus, res.results, 'Pilih kampus…');
            });

            $campus.on('change', function () {
                resetDownstream([$building, $floor, $room, $category, $subCategory]);
                clearLocationItem();
                if (partial) {
                    initLocationSelect();
                }
                var campusId = $campus.val();
                if (!campusId) {
                    return;
                }
                $.getJSON(urls.buildings, $.extend({ campus_id: campusId }, statusParams(root)))
                    .done(function (res) {
                        fillSelect($building, res.results, 'Pilih gedung…');
                    });
                if (partial) {
                    loadScopedCategories();
                }
            });

            $building.on('change', function () {
                resetDownstream([$floor, $room, $category, $subCategory]);
                clearLocationItem();
                var buildingId = $building.val();
                if (partial) {
                    if ($campus.val() || buildingId) {
                        loadScopedCategories();
                    }
                    initLocationSelect();
                }
                if (!buildingId) {
                    return;
                }
                $.getJSON(urls.floors, { building_id: buildingId })
                    .done(function (res) {
                        fillSelect($floor, res.results, 'Pilih lantai…');
                    });
            });

            $floor.on('change', function () {
                resetDownstream([$room, $category, $subCategory]);
                clearLocationItem();
                var floorId = $floor.val();
                if (partial) {
                    if ($campus.val() || $building.val() || floorId) {
                        loadScopedCategories();
                    }
                    initLocationSelect();
                }
                if (!floorId) {
                    return;
                }
                $.getJSON(urls.rooms, { floor_id: floorId })
                    .done(function (res) {
                        fillSelect($room, res.results, 'Pilih ruangan…');
                    });
            });

            $room.on('change', function () {
                resetDownstream([$category, $subCategory]);
                clearLocationItem();
                var roomId = $room.val();
                if (partial) {
                    loadScopedCategories();
                    initLocationSelect();
                    return;
                }
                if (!roomId) {
                    return;
                }
                $.getJSON(urls.categories, $.extend({ room_id: roomId }, statusParams(root)))
                    .done(function (res) {
                        fillSelect($category, res.results, 'Pilih kategori…');
                    });
            });

            $category.on('change', function () {
                resetDownstream([$subCategory]);
                clearLocationItem();
                if (partial) {
                    loadScopedSubcategories();
                    initLocationSelect();
                    return;
                }
                var roomId = $room.val();
                var categoryId = $category.val();
                if (!roomId || !categoryId) {
                    return;
                }
                $.getJSON(urls.subCategories, $.extend({
                    room_id: roomId,
                    category_id: categoryId,
                }, statusParams(root))).done(function (res) {
                    fillSelect($subCategory, res.results, 'Pilih sub kategori…');
                });
            });

            $subCategory.on('change', function () {
                if ($locItem.length) {
                    $locItem.val(null).trigger('change');
                    // Re-bind so empty q is allowed once cascade is complete
                    initLocationSelect();
                }
            });
        }

        function initLocationSelect() {
            if (!$locItem.length) {
                return;
            }
            bindSelect2($locItem, {
                url: urls.items,
                minimumInputLength: partial ? (hasScope() ? 0 : 2) : 0,
                data: function (params) {
                    var payload = {
                        q: params.term || '',
                        room_id: $room.val(),
                        category_id: $category.val(),
                        sub_category_id: $subCategory.val(),
                    };
                    if (partial) {
                        payload.partial = 1;
                        payload.campus_id = $campus.val();
                        payload.building_id = $building.val();
                        payload.floor_id = $floor.val();
                    }
                    return $.extend(payload, statusParams(root));
                },
            });
            $locItem.off('change.invsel').on('change.invsel', function () {
                if (currentMode !== 'location') {
                    return;
                }
                loadDetail(root, $locItem.val(), 'location');
            });
        }

        function initQuickSelect() {
            if (!$quickItem.length) {
                return;
            }
            bindSelect2($quickItem, {
                url: urls.search,
                minimumInputLength: 2,
                data: function (params) {
                    var payload = { q: params.term || '' };
                    if (partial) {
                        payload.campus_id = $campus.val();
                        payload.building_id = $building.val();
                        payload.floor_id = $floor.val();
                        payload.room_id = $room.val();
                        payload.category_id = $category.val();
                        payload.sub_category_id = $subCategory.val();
                    }
                    return $.extend(payload, statusParams(root));
                },
            });
            $quickItem.off('change.invsel').on('change.invsel', function () {
                if (modes === 'both' && currentMode !== 'quick') {
                    return;
                }
                if (modes === 'location') {
                    return;
                }
                loadDetail(root, $quickItem.val(), 'quick');
            });
        }

        initLocationSelect();
        initQuickSelect();

        function resolveCode(code) {
            if (!code || !urls.resolveCode) {
                return;
            }
            $.ajax({
                url: urls.resolveCode,
                method: 'POST',
                data: {
                    code: code,
                    _token: csrf,
                    maintenance: root.getAttribute('data-resolve-maintenance') === '1' ? 1 : 0,
                },
                dataType: 'json',
            }).done(function (res) {
                if (!res.found || !res.item) {
                    return;
                }
                var item = res.item;
                if ($quickItem.length) {
                    var option = new Option(
                        item.inventory_number + ' — ' + item.name,
                        item.id,
                        true,
                        true
                    );
                    $quickItem.append(option).trigger('change');
                }
                if (modes === 'both') {
                    setMode('quick');
                }
                applySelection(root, item, { source: 'qr' });
            });
        }

        if (showQr) {
            var cameraOn = false;
            var html5QrCode = null;
            var $toggle = $(root).find('[data-invsel-toggle-camera]');
            var $reader = $(root).find('[data-invsel-qr-reader]');
            var $label = $(root).find('[data-invsel-camera-label]');

            $toggle.on('click', function () {
                if (typeof Html5Qrcode === 'undefined') {
                    alert('Library QR tidak tersedia.');
                    return;
                }
                if (!cameraOn) {
                    if (!$reader.attr('id')) {
                        $reader.attr('id', 'qr-' + root.id);
                    }
                    $reader.removeClass('d-none');
                    html5QrCode = new Html5Qrcode($reader.attr('id'));
                    html5QrCode.start(
                        { facingMode: 'environment' },
                        { fps: 8, qrbox: { width: 220, height: 220 } },
                        function (decoded) {
                            resolveCode(decoded);
                            if (html5QrCode && cameraOn) {
                                html5QrCode.stop().then(function () {
                                    cameraOn = false;
                                    $label.text('Mulai Kamera');
                                    $reader.addClass('d-none');
                                }).catch(function () {});
                            }
                        },
                        function () {}
                    ).then(function () {
                        cameraOn = true;
                        $label.text('Stop Kamera');
                    }).catch(function (err) {
                        alert('Kamera tidak dapat dibuka: ' + (err && err.message ? err.message : err));
                        $reader.addClass('d-none');
                    });
                } else if (html5QrCode) {
                    html5QrCode.stop().then(function () {
                        cameraOn = false;
                        $label.text('Mulai Kamera');
                        $reader.addClass('d-none');
                    }).catch(function () {});
                }
            });

            $(root).find('[data-invsel-resolve-code]').on('click', function () {
                var code = $(root).find('[data-invsel-manual-code]').val();
                resolveCode(code);
            });
        }

        // Prefill detail when hidden already has a value; otherwise lock submit if required.
        var initialId = root.querySelector('[data-invsel-hidden]')?.value;
        if (initialId) {
            loadDetail(root, initialId, 'initial');
        } else {
            applySelection(root, null, { source: 'init' });
        }
    }

    function boot() {
        document.querySelectorAll('[data-inventory-selector]').forEach(initOne);
    }

    $(boot);
    window.InventorySelector = { init: boot, initOne: initOne };
})(window, window.jQuery);
