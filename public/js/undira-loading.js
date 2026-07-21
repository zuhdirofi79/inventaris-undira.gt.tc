/**
 * UNDIRA Loading helpers — page overlay, AJAX bar, inline spinner, button loading.
 * No business logic. Depends on jQuery when available for AJAX hooks.
 */
(function (window, document) {
  'use strict';

  var STORAGE_KEY = 'undiraNavLoading';
  var ajaxCount = 0;
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

  function showPageOverlay(title, sub) {
    var el = overlayEl();
    if (!el) return;
    setOverlayText(title || 'Memuat halaman...', sub || 'Sedang mengambil data...');
    el.classList.add('active', 'undira-overlay-show');
    el.setAttribute('aria-busy', 'true');
  }

  function hidePageOverlay() {
    var el = overlayEl();
    if (!el) return;
    el.classList.remove('active', 'undira-overlay-show');
    el.setAttribute('aria-busy', 'false');
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

  function isInternalNavLink(a) {
    if (!a || a.tagName !== 'A') return false;
    var href = a.getAttribute('href');
    if (!href || href === '#' || href.indexOf('javascript:') === 0) return false;
    if (a.target === '_blank' || a.hasAttribute('download')) return false;
    if (a.dataset.undiraNoLoading === '1') return false;
    if (a.getAttribute('data-widget') === 'pushmenu') return false;
    if (a.getAttribute('data-bs-toggle')) return false;
    try {
      var url = new URL(a.href, window.location.origin);
      if (url.origin !== window.location.origin) return false;
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
      // Sidebar / navbar / breadcrumb / in-content links
      var inNav =
        a.closest('.main-sidebar') ||
        a.closest('.main-header') ||
        a.closest('.content-wrapper') ||
        a.closest('.breadcrumb');
      if (!inNav) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

      e.preventDefault();
      try {
        sessionStorage.setItem(STORAGE_KEY, '1');
      } catch (err) { /* ignore */ }
      showPageOverlay('Memuat halaman...', 'Sedang mengambil data...');
      window.location.href = a.href;
    }, true);
  }

  function finishInitialLoad() {
    var hadNav = false;
    try {
      hadNav = sessionStorage.getItem(STORAGE_KEY) === '1';
      if (hadNav) sessionStorage.removeItem(STORAGE_KEY);
    } catch (err) { /* ignore */ }

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

    if (document.readyState === 'complete') {
      window.setTimeout(reveal, hadNav ? 180 : 40);
    } else {
      window.addEventListener('load', function () {
        window.setTimeout(reveal, hadNav ? 180 : 40);
      });
    }
  }

  function bindAjaxHooks() {
    if (!window.jQuery) return;
    var $jq = window.jQuery;

    $jq(document).ajaxSend(function (_e, _xhr, settings) {
      if (settings.undiraSkipLoading) return;
      var url = settings.url || '';
      // Polling notifikasi & background ringan — tanpa indikator
      if (url.indexOf('notifications') !== -1) return;
      // Full overlay only when explicitly requested
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

    $jq(document).ajaxComplete(function (_e, _xhr, settings) {
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
    });
  }

  // Public API (compatible with existing showLoading / hideLoading)
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
    finishInitialLoad();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
