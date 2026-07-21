/**
 * UNDIRA Loading helpers — page overlay, AJAX bar, inline spinner, button loading.
 * Progressive enhancement: links keep valid href; overlay is cosmetic only.
 */
(function (window, document) {
  'use strict';

  var STORAGE_KEY = 'undiraNavLoading';
  var SAFETY_MS = 12000;
  var SHOW_DELAY_MS = 180;
  var ajaxCount = 0;
  var safetyTimer = null;
  var showTimer = null;
  var logoUrl = (window.UNDIRA_LOADING && window.UNDIRA_LOADING.logo) || '/images/undira.png';

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function overlayEl() {
    return $('#loadingOverlay');
  }

  function ajaxBarEl() {
    var el = $('#undiraAjaxBar');
    if (!el) {
      el = document.createElement('div');
      el.id = 'undiraAjaxBar';
      el.className = 'undira-ajax-bar';
      el.setAttribute('aria-hidden', 'true');
      document.body.appendChild(el);
    }
    return el;
  }

  function setOverlayText(title, sub) {
    var t = $('#undiraLoaderText');
    var s = $('#undiraLoaderSub');
    if (t && title) t.textContent = title;
    if (s) s.textContent = sub || 'Mohon tunggu sebentar';
  }

  function clearTimers() {
    if (showTimer) {
      window.clearTimeout(showTimer);
      showTimer = null;
    }
    if (safetyTimer) {
      window.clearTimeout(safetyTimer);
      safetyTimer = null;
    }
  }

  function showPageOverlay(title, sub, options) {
    // Never cover an interactive Bootstrap modal with the page loader.
    if (document.body.classList.contains('modal-open') || document.querySelector('.modal.show')) {
      return;
    }
    var el = overlayEl();
    if (!el) return;
    var opts = options || {};
    var delay = typeof opts.delay === 'number' ? opts.delay : 0;

    function apply() {
      if (document.body.classList.contains('modal-open') || document.querySelector('.modal.show')) {
        return;
      }
      setOverlayText(title || 'Memuat halaman...', sub || 'Sedang mengambil data...');
      el.classList.add('active', 'undira-overlay-show');
      el.setAttribute('aria-busy', 'true');
      if (safetyTimer) window.clearTimeout(safetyTimer);
      safetyTimer = window.setTimeout(function () {
        hidePageOverlay();
      }, SAFETY_MS);
    }

    if (delay > 0) {
      if (showTimer) window.clearTimeout(showTimer);
      showTimer = window.setTimeout(apply, delay);
      return;
    }

    apply();
  }

  function hidePageOverlay() {
    clearTimers();
    var el = overlayEl();
    if (!el) return;
    el.classList.remove('active', 'undira-overlay-show');
    el.setAttribute('aria-busy', 'false');
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch (err) { /* ignore */ }
  }

  function cleanupStaleModalState() {
    if (document.querySelector('.modal.show')) return;
    document.querySelectorAll('.modal-backdrop').forEach(function (node) {
      node.parentNode && node.parentNode.removeChild(node);
    });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
  }

  function bindModalSafety() {
    document.addEventListener('show.bs.modal', function () {
      hidePageOverlay();
    });
    document.addEventListener('shown.bs.modal', function () {
      hidePageOverlay();
    });
    document.addEventListener('hidden.bs.modal', function () {
      cleanupStaleModalState();
    });
  }

  function showAjaxBar() {
    var bar = ajaxBarEl();
    bar.classList.remove('done');
    bar.classList.add('active');
  }

  function hideAjaxBar() {
    var bar = ajaxBarEl();
    bar.classList.add('done');
    window.setTimeout(function () {
      bar.classList.remove('active', 'done');
      bar.style.width = '';
    }, 350);
  }

  function showInlineLoading(target) {
    if (!target) return;
    var el = typeof target === 'string' ? $(target) : target;
    if (!el) return;
    el.classList.add('undira-inline-loading');
    if (!el.querySelector('.undira-inline-spinner')) {
      var spin = document.createElement('span');
      spin.className = 'undira-inline-spinner';
      spin.setAttribute('aria-hidden', 'true');
      el.appendChild(spin);
    }
  }

  function hideInlineLoading(target) {
    if (!target) return;
    var el = typeof target === 'string' ? $(target) : target;
    if (!el) return;
    el.classList.remove('undira-inline-loading');
    var spin = el.querySelector('.undira-inline-spinner');
    if (spin) spin.remove();
  }

  function setButtonLoading(btn, loading, label) {
    if (!btn) return;
    if (loading) {
      if (!btn.dataset.undiraOriginalHtml) {
        btn.dataset.undiraOriginalHtml = btn.innerHTML;
      }
      btn.disabled = true;
      btn.classList.add('btn-undira-loading', 'is-loading');
      btn.setAttribute('aria-busy', 'true');
      btn.innerHTML =
        '<span class="btn-loading-label">' +
        '<span class="spinner-border spinner-border-sm text-light" role="status" aria-hidden="true"></span>' +
        '<span>' + (label || 'Memproses...') + '</span>' +
        '</span>';
    } else {
      btn.disabled = false;
      btn.classList.remove('btn-undira-loading', 'is-loading');
      btn.removeAttribute('aria-busy');
      if (btn.dataset.undiraOriginalHtml) {
        btn.innerHTML = btn.dataset.undiraOriginalHtml;
        delete btn.dataset.undiraOriginalHtml;
      }
    }
  }

  function isExcludedPath(pathname) {
    return /\/inventory\/[^/]+\/(qr|barcode)(\/download)?$/.test(pathname)
      || /\/inventory\/[^/]+\/label\/print$/.test(pathname)
      || /\/scan\//.test(pathname);
  }

  function isInternalNavLink(a) {
    if (!a || a.tagName !== 'A') return false;
    var href = a.getAttribute('href');
    if (!href || href === '#' || href.indexOf('javascript:') === 0) return false;
    if (a.target === '_blank' || a.hasAttribute('download')) return false;
    if (a.dataset.undiraNoLoading === '1' || a.getAttribute('data-no-loader') === '1') return false;
    if (a.classList.contains('no-page-loader')) return false;
    if (a.getAttribute('data-widget') === 'pushmenu') return false;
    if (a.getAttribute('data-bs-toggle')) return false;
    if (a.getAttribute('data-bs-dismiss')) return false;
    if (a.classList.contains('btn-manage-option')) return false;
    if (a.closest('.modal') || a.closest('.dropdown-menu') || a.closest('.modal-backdrop')) return false;
    try {
      var url = new URL(a.href, window.location.origin);
      if (url.origin !== window.location.origin) return false;
      if (isExcludedPath(url.pathname)) return false;
      if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
        return false;
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  function bindPageTransitions() {
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('a');
      if (!isInternalNavLink(a)) return;
      var inNav =
        a.closest('.main-sidebar') ||
        a.closest('.main-header') ||
        a.closest('.content-wrapper') ||
        a.closest('.breadcrumb');
      if (!inNav) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;

      // Do NOT preventDefault — keep progressive enhancement.
      // Overlay is cosmetic; browser navigates via real href.
      try {
        sessionStorage.setItem(STORAGE_KEY, '1');
      } catch (err) { /* ignore */ }
      showPageOverlay('Memuat halaman...', 'Sedang mengambil data...', { delay: SHOW_DELAY_MS });
    }, true);
  }

  function finishInitialLoad() {
    var hadNav = false;
    try {
      hadNav = sessionStorage.getItem(STORAGE_KEY) === '1';
      sessionStorage.removeItem(STORAGE_KEY);
    } catch (err) { /* ignore */ }

    // Always clear any restored/stale overlay first.
    hidePageOverlay();

    if (hadNav) {
      showPageOverlay('Memuat halaman...', 'Sedang mengambil data...');
    }

    function reveal() {
      hidePageOverlay();
      var content = $('.content .container-fluid') || $('.content') || $('#dashboardRealContent');
      if (content && !content.classList.contains('undira-page-content')) {
        content.classList.add('undira-page-content');
      }
      if (typeof window.undiraDashboardReveal === 'function') {
        window.undiraDashboardReveal();
      }
    }

    // Hide on DOM ready — do not wait for images (broken/slow assets used to stick the loader).
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        window.setTimeout(reveal, hadNav ? 120 : 0);
      });
    } else {
      window.setTimeout(reveal, hadNav ? 120 : 0);
    }

    window.addEventListener('load', hidePageOverlay);
    window.addEventListener('pageshow', function () {
      hidePageOverlay();
    });
    window.addEventListener('popstate', hidePageOverlay);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') hidePageOverlay();
    });
  }

  function bindAjaxHooks() {
    if (!window.jQuery) return;
    var $jq = window.jQuery;

    $jq(document).ajaxSend(function (_e, _xhr, settings) {
      if (settings.undiraSkipLoading) return;
      var url = settings.url || '';
      if (url.indexOf('notifications') !== -1) return;
      if (settings.undiraOverlay) {
        showPageOverlay(settings.undiraOverlayTitle || 'Memuat data...', 'Mohon tunggu sebentar');
        return;
      }
      if (settings.undiraTarget) {
        var t = typeof settings.undiraTarget === 'string'
          ? document.querySelector(settings.undiraTarget)
          : settings.undiraTarget;
        showInlineLoading(t);
        settings._undiraInlineTarget = t;
        return;
      }
      ajaxCount += 1;
      if (ajaxCount === 1) showAjaxBar();
    });

    function finishAjax(settings) {
      if (settings.undiraSkipLoading) return;
      var url = settings.url || '';
      if (url.indexOf('notifications') !== -1) return;
      if (settings.undiraOverlay) {
        hidePageOverlay();
        return;
      }
      if (settings._undiraInlineTarget) {
        hideInlineLoading(settings._undiraInlineTarget);
        return;
      }
      if (settings.undiraTarget) return;
      ajaxCount = Math.max(0, ajaxCount - 1);
      if (ajaxCount === 0) hideAjaxBar();
    }

    $jq(document).ajaxComplete(function (_e, _xhr, settings) {
      finishAjax(settings);
    });

    $jq(document).ajaxError(function (_e, _xhr, settings) {
      finishAjax(settings);
      hidePageOverlay();
    });
  }

  window.showLoading = function (title, sub) {
    showPageOverlay(title || 'Memuat halaman...', sub || 'Mohon tunggu sebentar');
  };
  window.hideLoading = function () {
    hidePageOverlay();
  };
  window.showInlineLoading = showInlineLoading;
  window.hideInlineLoading = hideInlineLoading;
  window.setButtonLoading = setButtonLoading;
  window.undiraShowPageOverlay = showPageOverlay;
  window.undiraHidePageOverlay = hidePageOverlay;

  function boot() {
    if (window.__undiraLoadingBooted) return;
    window.__undiraLoadingBooted = true;
    bindPageTransitions();
    bindAjaxHooks();
    bindModalSafety();
    finishInitialLoad();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
