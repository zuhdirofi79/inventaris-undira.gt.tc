/**
 * Fills [data-inventory-maintenance-context] from InventorySelector selection
 * or initial server-rendered payload.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    function textOrDash(v) {
        return v == null || v === '' ? '—' : v;
    }

    function renderList($ul, rows, emptyText, mapFn) {
        $ul.empty();
        if (!rows || !rows.length) {
            $ul.append($('<li class="text-muted">').text(emptyText));
            return;
        }
        rows.forEach(function (row) {
            $ul.append(mapFn(row));
        });
    }

    function fillPanel(panel, ctx) {
        if (!panel || !ctx || !ctx.id) {
            if (panel) {
                panel.classList.add('d-none');
            }
            return;
        }
        panel.classList.remove('d-none');

        var $p = $(panel);
        $p.find('[data-mc-status]').text(textOrDash(ctx.status_label));
        $p.find('[data-mc-number]').text(textOrDash(ctx.inventory_number));
        $p.find('[data-mc-name]').text(textOrDash(ctx.name));
        $p.find('[data-mc-condition]').text(textOrDash(ctx.condition_label));
        $p.find('[data-mc-pic]').text(textOrDash(ctx.pic));
        $p.find('[data-mc-warranty]').text(textOrDash(ctx.warranty_label));
        $p.find('[data-mc-location]').text(textOrDash(ctx.location));

        var photo = $p.find('[data-mc-photo]');
        var photoEmpty = $p.find('[data-mc-photo-empty]');
        if (ctx.photo_url) {
            photo.attr('src', ctx.photo_url).removeClass('d-none');
            photoEmpty.addClass('d-none');
        } else {
            photo.addClass('d-none').attr('src', '');
            photoEmpty.removeClass('d-none');
        }

        var latest = ctx.latest_work_order;
        var $wo = $p.find('[data-mc-latest-wo]');
        if (latest) {
            $wo.html(
                '<div class="fw-semibold"></div>' +
                '<div></div>' +
                '<div class="text-muted"></div>' +
                '<a href="#" class="small" data-mc-latest-wo-link>Lihat</a>'
            );
            $wo.find('.fw-semibold').text(latest.maintenance_number || '—');
            $wo.find('div').eq(1).text((latest.type_label || '—') + ' · ' + (latest.status_label || '—'));
            $wo.find('.text-muted').text((latest.service_date || '—') + ' · ' + (latest.technician || '—'));
            $wo.find('[data-mc-latest-wo-link]').attr('href', latest.url || '#');
        } else {
            $wo.html('<span class="text-muted">Belum ada work order.</span>');
        }

        renderList($p.find('[data-mc-schedules]'), ctx.maintenance_schedules, 'Tidak ada jadwal aktif.', function (sch) {
            return $('<li class="border-bottom py-1">')
                .append($('<a>').attr('href', sch.url || '#').text(sch.type_label || '—'))
                .append(
                    $('<div class="text-muted">').text(
                        'Berikutnya: ' + (sch.next_maintenance_date || '—') + ' · PIC ' + (sch.responsible_pic || '—')
                    )
                );
        });

        renderList($p.find('[data-mc-history]'), ctx.maintenance_history, 'Belum ada riwayat.', function (row) {
            return $('<li class="border-bottom py-1">')
                .append($('<a>').attr('href', row.url || '#').text(row.maintenance_number || '—'))
                .append(
                    $('<div class="text-muted">').text(
                        (row.service_date || '—') + ' · ' + (row.type_label || '—') + ' · ' + (row.status_label || '—')
                    )
                );
        });
    }

    function bindSelector(selectorEl, panel) {
        var baseUrl = panel.getAttribute('data-context-url') || '/inventory/selector/items';

        $(selectorEl).on('inventory-selector:selected', function (_e, detail) {
            if (!detail || !detail.id) {
                fillPanel(panel, null);
                $(document).trigger('inventory-maintenance-context:cleared');
                return;
            }

            // If detail already includes maintenance_history (from resolve with ?maintenance=1), use it.
            if (Array.isArray(detail.maintenance_history)) {
                fillPanel(panel, detail);
                $(document).trigger('inventory-maintenance-context:loaded', [detail]);
                return;
            }

            $.getJSON(baseUrl + '/' + detail.id + '/maintenance-context')
                .done(function (ctx) {
                    fillPanel(panel, ctx);
                    $(document).trigger('inventory-maintenance-context:loaded', [ctx]);
                })
                .fail(function () {
                    fillPanel(panel, null);
                });
        });
    }

    function boot() {
        var panel = document.querySelector('[data-inventory-maintenance-context]');
        if (!panel) {
            return;
        }
        var selector = document.querySelector('[data-inventory-selector]');
        if (selector) {
            bindSelector(selector, panel);
        }
    }

    $(boot);
    window.InventoryMaintenanceContext = { fill: fillPanel, boot: boot };
})(window, window.jQuery);
