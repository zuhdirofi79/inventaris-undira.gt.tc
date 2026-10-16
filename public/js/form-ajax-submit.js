/**
 * Submit form via fetch/FormData so validation 422 keeps DOM, inputs, and file selections.
 * Opt-in: <form data-ajax-submit ...>
 */
(function (window, document) {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function nameFromKey(key) {
        var parts = String(key).split('.');
        var name = parts[0];
        for (var i = 1; i < parts.length; i += 1) {
            name += '[' + parts[i] + ']';
        }
        return name;
    }

    function findFields(form, key) {
        var name = nameFromKey(key);
        var nodes = form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]');
        if (nodes.length) {
            return Array.prototype.slice.call(nodes);
        }
        if (key.indexOf('.') === -1) {
            nodes = form.querySelectorAll('[name^="' + key + '["]');
            if (nodes.length) {
                return Array.prototype.slice.call(nodes);
            }
        }
        return [];
    }

    function findBag(form, key) {
        var exact = form.querySelector('[data-error-bag="' + key + '"]');
        if (exact) {
            return exact;
        }
        var aliases = {
            items: ['items', 'inventory_item_id', 'inventory_item_ids'],
            inventory_item_id: ['items', 'inventory_item_id'],
            inventory_item_ids: ['items', 'inventory_item_ids'],
            passengers: ['passengers'],
        };
        var list = aliases[key] || [key];
        for (var i = 0; i < list.length; i += 1) {
            var bag = form.querySelector('[data-error-bag="' + list[i] + '"]');
            if (bag) {
                return bag;
            }
        }
        return null;
    }

    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('[data-ajax-error]').forEach(function (el) {
            el.remove();
        });
        var summary = document.querySelector('[data-ajax-error-summary]');
        if (summary) {
            summary.remove();
        }
    }

    function showSummary(form, message, errors) {
        var host = form.closest('.card, .content, main, body') || form.parentElement;
        if (!host) {
            return;
        }
        var box = document.createElement('div');
        box.className = 'alert alert-danger alert-dismissible fade show';
        box.setAttribute('role', 'alert');
        box.setAttribute('data-ajax-error-summary', '1');
        var list = '';
        Object.keys(errors || {}).forEach(function (key) {
            (errors[key] || []).forEach(function (msg) {
                list += '<li>' + escapeHtml(msg) + '</li>';
            });
        });
        box.innerHTML =
            '<i class="fas fa-exclamation-circle me-2"></i><strong>Terjadi kesalahan:</strong>' +
            (list ? '<ul class="mb-0 mt-2">' + list + '</ul>' : '<div class="mt-2">' + escapeHtml(message || 'Data yang dimasukkan belum valid.') + '</div>') +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>';
        host.insertBefore(box, host.firstChild);
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function attachError(el, message) {
        el.classList.add('is-invalid');
        var feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        feedback.setAttribute('data-ajax-error', '1');
        feedback.textContent = message;
        var parent = el.closest('.col-md-2, .col-md-3, .col-md-4, .col-md-6, .col-md-9, .col-md-12, .col-lg-4, .col-12, .mb-3, .mb-0, td, .form-check') || el.parentElement;
        if (parent) {
            parent.appendChild(feedback);
        } else {
            el.insertAdjacentElement('afterend', feedback);
        }
    }

    function applyErrors(form, errors) {
        var first = null;
        Object.keys(errors || {}).forEach(function (key) {
            var messages = errors[key] || [];
            if (!messages.length) {
                return;
            }
            var bag = findBag(form, key);
            if (bag) {
                messages.forEach(function (message) {
                    var node = document.createElement('div');
                    node.className = 'text-danger small mb-2';
                    node.setAttribute('data-ajax-error', '1');
                    node.textContent = message;
                    bag.appendChild(node);
                });
                if (!first) {
                    first = bag;
                }
                return;
            }
            var fields = findFields(form, key);
            if (!fields.length) {
                return;
            }
            fields.forEach(function (field) {
                attachError(field, messages[0]);
                if (!first) {
                    first = field;
                }
            });
        });
        if (first && typeof first.focus === 'function') {
            try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
        }
        if (first && typeof first.scrollIntoView === 'function') {
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function setBusy(form, busy) {
        form.dataset.ajaxBusy = busy ? '1' : '0';
        var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        buttons.forEach(function (btn) {
            if (busy) {
                btn.dataset.ajaxWasDisabled = btn.disabled ? '1' : '0';
                btn.disabled = true;
                if (typeof window.setButtonLoading === 'function' && btn.tagName === 'BUTTON') {
                    window.setButtonLoading(btn, true, btn.getAttribute('data-loading-text') || 'Menyimpan...');
                }
            } else {
                if (typeof window.setButtonLoading === 'function' && btn.tagName === 'BUTTON') {
                    window.setButtonLoading(btn, false);
                }
                if (btn.dataset.ajaxWasDisabled !== '1') {
                    btn.disabled = false;
                }
            }
        });
    }

    function bind(form) {
        if (form.dataset.ajaxSubmitBound === '1') {
            return;
        }
        form.dataset.ajaxSubmitBound = '1';

        form.addEventListener('input', function (event) {
            var target = event.target;
            if (!target || !target.classList || !target.classList.contains('is-invalid')) {
                return;
            }
            target.classList.remove('is-invalid');
            var wrap = target.closest('.col-md-2, .col-md-3, .col-md-4, .col-md-6, .col-md-9, .col-md-12, .col-lg-4, .col-12, .mb-3, .mb-0, td, .form-check') || target.parentElement;
            if (wrap) {
                wrap.querySelectorAll('[data-ajax-error]').forEach(function (el) { el.remove(); });
            }
        }, true);

        form.addEventListener('submit', function (event) {
            if (form.dataset.ajaxSubmit === '0') {
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            if (form.dataset.ajaxBusy === '1') {
                return;
            }

            if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                return;
            }

            clearErrors(form);
            setBusy(form, true);

            var action = form.getAttribute('action') || window.location.href;
            var method = (form.getAttribute('method') || 'POST').toUpperCase();
            var data = new FormData(form);
            var headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            };

            fetch(action, {
                method: method === 'GET' ? 'POST' : method,
                headers: headers,
                body: data,
                credentials: 'same-origin',
            }).then(function (response) {
                return response.text().then(function (text) {
                    var payload = null;
                    try {
                        payload = text ? JSON.parse(text) : null;
                    } catch (e) {
                        payload = null;
                    }
                    return { response: response, payload: payload, text: text };
                });
            }).then(function (result) {
                var response = result.response;
                var payload = result.payload || {};

                if (response.status === 422) {
                    setBusy(form, false);
                    var errors = payload.errors || {};
                    applyErrors(form, errors);
                    showSummary(form, payload.message || 'Data yang dimasukkan belum valid.', errors);
                    return;
                }

                if (!response.ok) {
                    setBusy(form, false);
                    showSummary(form, payload.message || 'Permintaan gagal diproses. Silakan coba lagi.', {});
                    return;
                }

                var redirect = payload.redirect || response.headers.get('X-Redirect') || form.getAttribute('data-success-url');
                if (redirect) {
                    window.location.assign(redirect);
                    return;
                }

                setBusy(form, false);
                if (payload.message && typeof window.showToast === 'function') {
                    window.showToast(payload.message, 'success');
                }
            }).catch(function () {
                setBusy(form, false);
                showSummary(form, 'Koneksi gagal. Periksa jaringan Anda lalu coba lagi.', {});
            });
        });
    }

    function boot() {
        document.querySelectorAll('form[data-ajax-submit]').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.UndiraAjaxForm = { bind: bind, clearErrors: clearErrors };
})(window, document);
