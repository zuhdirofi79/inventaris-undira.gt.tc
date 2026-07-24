/**
 * Page header keyboard shortcuts (Inventaris UNDIRA).
 * Esc = back | Ctrl+S = save | Ctrl+/ = search | Ctrl+Shift+F = filter
 */
(function () {
    'use strict';

    function headerEl() {
        return document.querySelector('[data-page-header]');
    }

    function isTypingTarget(el) {
        if (!el) return false;
        const tag = (el.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
        return !!el.isContentEditable;
    }

    function goBack() {
        const root = headerEl();
        if (!root) return;
        const btn = root.querySelector('[data-page-back]');
        if (btn && btn.getAttribute('href')) {
            window.location.href = btn.getAttribute('href');
            return;
        }
        const url = root.getAttribute('data-back-url');
        if (url) window.location.href = url;
    }

    function saveForm(root) {
        const selector = root.getAttribute('data-form-selector') || 'form';
        const form = document.querySelector(selector) || document.querySelector('form[method="post"], form[method="POST"]');
        if (!form) return;
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
            submitBtn.click();
            return;
        }
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function focusSearch(root) {
        const selector = root.getAttribute('data-search-selector');
        const input = (selector && document.querySelector(selector)) || document.querySelector('input[type="search"], .dataTables_filter input');
        if (!input) return;
        input.focus();
        if (typeof input.select === 'function') input.select();
    }

    function triggerFilter(root) {
        const selector = root.getAttribute('data-filter-selector');
        const btn = (selector && document.querySelector(selector)) || document.querySelector('#btnApplyFilter');
        if (btn) btn.click();
    }

    document.addEventListener('keydown', function (e) {
        const root = headerEl();
        if (!root) return;

        if (e.key === 'Escape') {
            if (isTypingTarget(e.target)) {
                e.target.blur();
                return;
            }
            e.preventDefault();
            goBack();
            return;
        }

        const mod = e.ctrlKey || e.metaKey;
        if (!mod) return;

        if (e.key === 's' || e.key === 'S') {
            e.preventDefault();
            saveForm(root);
            return;
        }

        if (e.key === '/') {
            e.preventDefault();
            focusSearch(root);
            return;
        }

        if (e.shiftKey && (e.key === 'f' || e.key === 'F')) {
            e.preventDefault();
            triggerFilter(root);
        }
    });
})();
