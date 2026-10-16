<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        use App\Support\AppSettings;
        try {
            $undiraAppName = AppSettings::appName();
            $undiraLogoUrl = AppSettings::logoUrl();
            $undiraFooterText = AppSettings::footerText();
            $undiraUniversityName = AppSettings::universityName();
        } catch (\Throwable $e) {
            $undiraAppName = (string) config('app.name', 'Inventaris UNDIRA');
            $undiraLogoUrl = asset('images/LOGO UNDIRA.png');
            $undiraFooterText = '© '.date('Y').' Biro PPPA. Universitas Dian Nusantara';
            $undiraUniversityName = 'Universitas Dian Nusantara';
        }
    @endphp
    <title>@yield('title', $undiraAppName)</title>
    @include('partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    {{-- DataTables / Select2 / Chart.js are page-scoped via @stack + partials --}}
    <link rel="stylesheet" href="{{ asset('css/undira.css') }}">
    <link rel="stylesheet" href="{{ asset('css/undira-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/undira-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/undira-loading.css') }}">
    <link rel="stylesheet" href="{{ asset('css/undira-modals.css') }}">
    @stack('styles')
    {{-- Global Dark Mode last: overrides page-level hardcodes without touching Blade markup --}}
    <link rel="stylesheet" href="{{ asset('css/undira-dark.css') }}">
</head>
<body class="hold-transition sidebar-mini layout-fixed undira-erp">
<script>
/* Apply layout mode + saved theme before first paint (avoids FOUC). */
(function () {
    try {
        if (localStorage.getItem('undira-dark-mode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    } catch (e) { /* ignore */ }
    var w = window.innerWidth || document.documentElement.clientWidth || 1200;
    /* Overlay mode (tablet + mobile): start closed */
    if (w <= 1024) {
        document.body.classList.add('sidebar-collapse');
        document.body.classList.remove('sidebar-open');
    }
})();
</script>
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light navbar-undira">
        <ul class="navbar-nav align-items-center flex-nowrap min-w-0">
            <li class="nav-item">
                <a class="nav-link"
                   data-widget="pushmenu"
                   href="#"
                   role="button"
                   id="sidebarToggle"
                   title="Buka/tutup menu"
                   aria-label="Buka atau tutup menu navigasi"
                   aria-controls="main-sidebar"
                   aria-expanded="true">
                    <i class="fas fa-bars" aria-hidden="true"></i>
                </a>
            </li>
            <li class="nav-item min-w-0">
                <a href="{{ route('dashboard') }}" class="nav-link app-identity" title="LOADOUT">
                    <span class="app-identity-stack">
                        <span class="app-identity-title">LOADOUT</span>
                        <span class="app-identity-subtitle">
                            <em>(List of Operational Assets, Data, Organization, Utilization &amp; Tracking)</em>
                        </span>
                    </span>
                </a>
            </li>
        </ul>
        <ul class="navbar-nav ms-auto align-items-center flex-nowrap">
            <li class="nav-item">
                <a class="nav-link" href="#" id="darkModeToggle" title="Mode Gelap" aria-label="Alihkan mode gelap">
                    <i class="fas fa-moon" aria-hidden="true"></i>
                </a>
            </li>
            <li class="nav-item dropdown undira-notif">
                <a class="nav-link undira-notif-toggle"
                   data-bs-toggle="dropdown"
                   data-bs-auto-close="outside"
                   href="#"
                   id="notificationDropdown"
                   aria-label="Notifikasi"
                   aria-haspopup="true"
                   aria-expanded="false"
                   title="Notifikasi">
                    <i class="fas fa-bell" aria-hidden="true"></i>
                    <span class="undira-notif-badge" id="notificationCount" hidden>0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end undira-notif-menu"
                     id="notificationDropdownMenu"
                     aria-labelledby="notificationDropdown"
                     role="menu">
                    <div class="undira-notif-header">
                        <div class="undira-notif-header-title">
                            <span>Notifikasi</span>
                            <span class="undira-notif-header-count" id="notificationHeaderCount" hidden></span>
                        </div>
                        <button type="button"
                                class="undira-notif-mark-all"
                                id="notificationMarkAll"
                                hidden
                                title="Tandai semua dibaca"
                                aria-label="Tandai semua notifikasi sebagai dibaca">
                            Tandai semua dibaca
                        </button>
                    </div>
                    <div class="undira-notif-list" id="notificationList" role="none"></div>
                    <div class="undira-notif-footer">
                        <a href="{{ route('notifications.index') }}" class="undira-notif-footer-link" role="menuitem">
                            Lihat semua notifikasi
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-label="Menu akun">
                    <i class="fas fa-user-circle" aria-hidden="true"></i>
                    <span class="d-none d-md-inline ms-1">{{ auth()->user()->name }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <span class="dropdown-item-text">
                        <strong>{{ auth()->user()->name }}</strong>
                        @if(auth()->user()->roles->isNotEmpty())
                            <br><small class="text-muted">{{ auth()->user()->roles->first()->name }}</small>
                        @endif
                    </span>
                    <div class="dropdown-divider"></div>
                    <form action="{{ route('logout') }}" method="POST" class="px-0">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="fas fa-sign-out-alt me-2"></i> Keluar
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4 sidebar-dark-undira sidebar-no-expand" id="main-sidebar" aria-label="Navigasi utama">
        <a href="{{ route('dashboard') }}" class="brand-link undira-sidebar-brand" title="{{ $undiraAppName }}">
            <span class="undira-sidebar-logo-wrap">
                <img
                    src="{{ $undiraLogoUrl }}"
                    alt="Logo {{ $undiraUniversityName }}"
                    class="undira-sidebar-logo"
                    decoding="async"
                >
            </span>
            <span class="undira-brand-mark" aria-hidden="true">
                <img
                    src="{{ asset('images/undira.png') }}"
                    alt=""
                    width="32"
                    height="32"
                    decoding="async"
                >
            </span>
        </a>

        <div class="sidebar">
            <div id="sidebar-user-panel" class="user-panel user-panel-undira d-flex">
                <div class="image">
                    <div class="user-avatar" aria-hidden="true">
                        <i class="fas fa-user text-white"></i>
                    </div>
                </div>
                <div class="info">
                    <span class="user-name">{{ auth()->user()->name }}</span>
                    <span class="user-meta">
                        <span class="status-indicator"></span> Online
                        @if(auth()->user()->roles->isNotEmpty())
                            &middot; {{ auth()->user()->roles->first()->name }}
                        @endif
                    </span>
                </div>
            </div>

            @include('partials.sidebar')
        </div>
    </aside>

    <div class="content-wrapper">
        {{-- Legacy breadcrumb fallback (prefer breadcrumbs on <x-page-header>) --}}
        @hasSection('breadcrumb')
        <div class="content-header content-header--legacy-breadcrumb">
            <div class="container-fluid">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-undira">
                        @yield('breadcrumb')
                    </ol>
                </nav>
            </div>
        </div>
        @endif

        <section class="content">
            <div class="container-fluid">
                <div id="toast-container"></div>
                @include('components.alert')
                @yield('content')
            </div>
        </section>
    </div>

    <footer class="main-footer undira-footer">
        <div class="undira-footer__inner">
            <div class="undira-footer__left text-body-secondary">
                <span class="undira-footer__copy">{{ $undiraFooterText }}</span>
            </div>
            <div class="undira-footer__right text-body-secondary">
                <span class="undira-footer__meta">
                    <span class="undira-footer__credit" title="Developed by zwars">
                        <i class="fas fa-laptop-code" aria-hidden="true"></i>
                        Developed by zwars
                    </span>
                    <span class="undira-footer__sep" aria-hidden="true">•</span>
                    <span class="undira-footer__version">
                        <i class="fas fa-code-branch" aria-hidden="true"></i>
                        Version 1.0.0
                    </span>
                </span>
            </div>
        </div>
    </footer>
</div>

{{-- Modals must live outside .wrapper / .content-wrapper so Bootstrap backdrop
     cannot stack above them (AdminLTE stacking-context issue). --}}
@stack('modals')

<div class="undira-sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

<div class="loading-overlay" id="loadingOverlay" role="status" aria-live="polite" aria-busy="false">
    <div class="undira-loader">
        <div class="undira-loader-logo-wrap">
            <span class="undira-loader-ring" aria-hidden="true"></span>
            <img src="{{ asset('images/undira.png') }}" alt="Logo UNDIRA" class="undira-loader-logo">
        </div>
        <p class="undira-loader-text" id="undiraLoaderText">Memuat halaman...</p>
        <p class="undira-loader-sub" id="undiraLoaderSub">Sedang mengambil data...</p>
    </div>
</div>

<script>
window.UNDIRA_LOADING = {
    logo: @json(asset('images/undira.png'))
};
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
{{-- DataTables / Select2 / Chart.js are page-scoped via @stack('scripts') + partials --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/undira-loading.js') }}"></script>
<script src="{{ asset('js/form-ajax-submit.js') }}"></script>
<script>
$.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
});

function showToast(message, type = 'success') {
    if (!message) return;
    const bg = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : type === 'warning' ? 'bg-warning text-dark' : 'bg-info';
    const toast = $(`
        <div class="toast align-items-center text-white ${bg} border-0 show mb-2" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    $('#toast-container').append(toast);
    setTimeout(() => toast.remove(), 5000);
}

@if(session('success'))
showToast(@json(session('success')), 'success');
@endif
@if(session('error'))
showToast(@json(session('error')), 'error');
@endif
@if(session('warning'))
showToast(@json(session('warning')), 'warning');
@endif

/* showLoading / hideLoading disediakan oleh undira-loading.js (overlay logo UNDIRA) */

// Disable submit + spinner untuk form biasa (UX anti double-submit).
// File downloads (Excel/CSV/PDF) do not unload the page — restore the button.
$(document).on('submit', 'form:not([data-confirm]):not([data-no-loading])', function () {
    const form = this;
    const btn = form.querySelector('button[type="submit"]:not([disabled]), input[type="submit"]:not([disabled])');
    if (!btn || typeof setButtonLoading !== 'function') return;
    if (btn.classList.contains('is-loading')) return;

    const loadingText = btn.getAttribute('data-loading-text') || 'Menyimpan...';
    setButtonLoading(btn, true, loadingText);

    const formatEl = form.querySelector('[name="format"]:checked') || form.querySelector('input[name="format"][type="hidden"]') || form.querySelector('input[name="format"]');
    const format = (formatEl && formatEl.value ? String(formatEl.value) : '').toLowerCase();
    const isDownload = form.hasAttribute('data-download')
        || ['excel', 'csv', 'pdf', 'xlsx', 'xls'].indexOf(format) !== -1;

    if (!isDownload) return;

    // Attachment responses keep the current page alive; clear loading after download starts.
    const delay = format === 'pdf' ? 4000 : 2000;
    window.setTimeout(function () {
        if (typeof setButtonLoading === 'function') {
            setButtonLoading(btn, false);
        }
    }, delay);
});

// Safety: restore any stuck loading buttons after bfcache / focus return.
window.addEventListener('pageshow', function () {
    if (typeof setButtonLoading !== 'function') return;
    document.querySelectorAll('button.is-loading, input.is-loading').forEach(function (btn) {
        setButtonLoading(btn, false);
    });
});

// Dark mode toggle
(function() {
    const body = document.body;
    const toggle = document.getElementById('darkModeToggle');
    if (localStorage.getItem('undira-dark-mode') === 'true') {
        body.classList.add('dark-mode');
        toggle.querySelector('i').classList.replace('fa-moon', 'fa-sun');
    }
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        localStorage.setItem('undira-dark-mode', isDark);
        const icon = toggle.querySelector('i');
        icon.classList.toggle('fa-moon', !isDark);
        icon.classList.toggle('fa-sun', isDark);
    });
})();

// Notifications (navbar dropdown)
(function () {
    const unreadUrl = @json(route('notifications.unread'));
    const readAllUrl = @json(route('notifications.read-all'));
    const indexUrl = @json(route('notifications.index'));
    const readUrlTpl = @json(url('/notifications/__ID__/read'));
    const listEl = document.getElementById('notificationList');
    const badgeEl = document.getElementById('notificationCount');
    const headerCountEl = document.getElementById('notificationHeaderCount');
    const markAllBtn = document.getElementById('notificationMarkAll');
    if (!listEl || !badgeEl) return;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatBadge(count) {
        if (count > 99) return '99+';
        return String(count);
    }

    function setUnreadCount(count) {
        const n = Number(count) || 0;
        if (n > 0) {
            badgeEl.textContent = formatBadge(n);
            badgeEl.hidden = false;
            if (headerCountEl) {
                headerCountEl.textContent = formatBadge(n);
                headerCountEl.hidden = false;
            }
            if (markAllBtn) markAllBtn.hidden = false;
        } else {
            badgeEl.hidden = true;
            if (headerCountEl) headerCountEl.hidden = true;
            if (markAllBtn) markAllBtn.hidden = true;
        }
    }

    function emptyStateHtml() {
        return '' +
            '<div class="undira-notif-empty" role="status">' +
            '  <i class="fas fa-bell undira-notif-empty-icon" aria-hidden="true"></i>' +
            '  <p class="undira-notif-empty-title">Tidak ada notifikasi</p>' +
            '  <p class="undira-notif-empty-text">Semua aktivitas terbaru akan muncul di sini.</p>' +
            '</div>';
    }

    function itemHtml(n) {
        const unread = !!n.unread;
        const icon = escapeHtml(n.icon || 'fas fa-bell');
        const title = escapeHtml(n.title || 'Notifikasi');
        const subject = n.subject ? '<div class="undira-notif-subject">' + escapeHtml(n.subject) + '</div>' : '';
        const subtitle = n.subtitle ? '<div class="undira-notif-subtitle">' + escapeHtml(n.subtitle) + '</div>' : '';
        const summary = n.summary ? '<div class="undira-notif-summary">' + escapeHtml(n.summary) + '</div>' : '';
        const time = escapeHtml(n.time_ago || '');
        const url = escapeHtml(n.url || indexUrl);
        const id = escapeHtml(n.id || '');

        return '' +
            '<a href="' + url + '" class="undira-notif-item' + (unread ? ' is-unread' : '') + '"' +
            '   role="menuitem" data-notif-id="' + id + '" data-notif-url="' + url + '">' +
            '  <span class="undira-notif-icon" aria-hidden="true"><i class="' + icon + '"></i></span>' +
            '  <span class="undira-notif-body">' +
            '    <span class="undira-notif-title-row">' +
            '      <span class="undira-notif-title">' + title + '</span>' +
            (unread ? '<span class="undira-notif-dot" title="Belum dibaca" aria-label="Belum dibaca"></span>' : '') +
            '    </span>' +
            subject + subtitle + summary +
            (time ? '<span class="undira-notif-time">' + time + '</span>' : '') +
            '  </span>' +
            '</a>';
    }

    function renderList(notifications) {
        if (!notifications || !notifications.length) {
            listEl.innerHTML = emptyStateHtml();
            return;
        }
        listEl.innerHTML = notifications.map(itemHtml).join('');
    }

    window.loadNotifications = function loadNotifications() {
        $.get(unreadUrl, function (data) {
            setUnreadCount(data.count || 0);
            renderList(data.notifications || []);
        }).fail(function () {
            /* keep last rendered state on transient errors */
        });
    };

    listEl.addEventListener('click', function (e) {
        const item = e.target.closest('.undira-notif-item');
        if (!item) return;
        const id = item.getAttribute('data-notif-id');
        const url = item.getAttribute('data-notif-url') || item.getAttribute('href');
        if (!id) return;
        e.preventDefault();
        $.post(readUrlTpl.replace('__ID__', encodeURIComponent(id)))
            .always(function () {
                window.location.href = url;
            });
    });

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            markAllBtn.disabled = true;
            $.post(readAllUrl)
                .done(function () {
                    setUnreadCount(0);
                    renderList([]);
                })
                .always(function () {
                    markAllBtn.disabled = false;
                });
        });
    }

    @if(auth()->check())
    var POLL_MS = 90000;
    var pollTimer = null;

    function startPolling() {
        if (pollTimer) return;
        pollTimer = window.setInterval(function () {
            if (document.visibilityState === 'hidden') return;
            window.loadNotifications();
        }, POLL_MS);
    }

    function stopPolling() {
        if (!pollTimer) return;
        window.clearInterval(pollTimer);
        pollTimer = null;
    }

    window.loadNotifications();
    startPolling();
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            window.loadNotifications();
            startPolling();
        } else {
            stopPolling();
        }
    });
    @endif
})();

// SweetAlert delete confirmation
$(document).on('submit', 'form[data-confirm]', function(e) {
    e.preventDefault();
    const form = this;
    Swal.fire({
        title: form.getAttribute('data-confirm-title') || 'Konfirmasi',
        text: form.getAttribute('data-confirm') || 'Yakin ingin melanjutkan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: form.getAttribute('data-confirm-color') || '#2E7D32',
        cancelButtonColor: '#6c757d',
        confirmButtonText: form.getAttribute('data-confirm-button') || 'Ya',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (!result.isConfirmed) return;
        const btn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (btn && typeof setButtonLoading === 'function' && !form.hasAttribute('data-no-loading')) {
            setButtonLoading(btn, true, btn.getAttribute('data-loading-text') || 'Memproses...');
        }
        form.submit();
    });
});

// Sidebar / overlay sync (AdminLTE PushMenu) — desktop rail + overlay drawer
(function () {
    const body = document.body;
    const sidebar = document.getElementById('main-sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
    const mqOverlay = window.matchMedia('(max-width: 1024px)');
    let userDesktopPreference = null; // null | 'expanded' | 'collapsed'
    let tooltipInstances = [];
    let floatCloseTimer = null;

    function isOverlayMode() {
        return mqOverlay.matches;
    }

    function isIconRail() {
        return !isOverlayMode() && body.classList.contains('sidebar-collapse');
    }

    function pushMenu(action) {
        if (isOverlayMode()) {
            if (action === 'collapse') {
                body.classList.remove('sidebar-open');
                body.classList.add('sidebar-collapse');
            } else if (action === 'expand') {
                body.classList.add('sidebar-collapse');
                body.classList.add('sidebar-open');
            } else if (action === 'toggle') {
                if (body.classList.contains('sidebar-open')) {
                    body.classList.remove('sidebar-open');
                    body.classList.add('sidebar-collapse');
                } else {
                    body.classList.add('sidebar-collapse');
                    body.classList.add('sidebar-open');
                }
            }
            return;
        }

        const $btn = window.jQuery ? window.jQuery('[data-widget="pushmenu"]') : null;
        if ($btn && $btn.length && typeof $btn.PushMenu === 'function') {
            if (action === 'toggle') {
                $btn.PushMenu('toggle');
            } else {
                $btn.PushMenu(action);
            }
            return;
        }

        if (action === 'collapse') {
            body.classList.remove('sidebar-open');
            body.classList.add('sidebar-collapse');
        } else if (action === 'expand') {
            body.classList.remove('sidebar-collapse');
            body.classList.remove('sidebar-open');
        } else if (action === 'toggle') {
            body.classList.toggle('sidebar-collapse');
            body.classList.remove('sidebar-open');
        }
    }

    function isExpanded() {
        if (isOverlayMode()) {
            return body.classList.contains('sidebar-open');
        }
        return !body.classList.contains('sidebar-collapse');
    }

    function destroyTooltips() {
        tooltipInstances.forEach(function (tip) {
            try { tip.dispose(); } catch (e) { /* ignore */ }
        });
        tooltipInstances = [];
        if (!sidebar) return;
        sidebar.querySelectorAll('.nav-sidebar > .nav-item > .nav-link[data-bs-toggle="tooltip"]').forEach(function (link) {
            link.removeAttribute('data-bs-toggle');
            link.removeAttribute('data-bs-placement');
            link.removeAttribute('data-bs-custom-class');
            link.removeAttribute('data-bs-title');
            if (link.dataset.undiraTitleBackup) {
                link.setAttribute('title', link.dataset.undiraTitleBackup);
            } else {
                link.removeAttribute('title');
            }
        });
    }

    function menuLabel(link) {
        const p = link.querySelector('p');
        if (!p) return (link.getAttribute('title') || '').trim();
        const clone = p.cloneNode(true);
        clone.querySelectorAll('i, .right, .badge').forEach(function (el) { el.remove(); });
        return (clone.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function syncTooltips() {
        destroyTooltips();
        if (!isIconRail() || !sidebar || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;

        sidebar.querySelectorAll('.nav-sidebar > .nav-item > .nav-link').forEach(function (link) {
            if (link.parentElement && link.parentElement.querySelector(':scope > .nav-treeview')) {
                return; /* parents use floating submenu instead of tooltip */
            }
            const label = menuLabel(link);
            if (!label) return;
            if (!link.dataset.undiraTitleBackup && link.getAttribute('title')) {
                link.dataset.undiraTitleBackup = link.getAttribute('title');
            }
            link.setAttribute('data-bs-toggle', 'tooltip');
            link.setAttribute('data-bs-placement', 'right');
            link.setAttribute('data-bs-custom-class', 'undira-sidebar-tooltip');
            link.setAttribute('data-bs-title', label);
            link.setAttribute('title', label);
            tooltipInstances.push(new bootstrap.Tooltip(link, {
                container: 'body',
                trigger: 'hover focus',
                delay: { show: 120, hide: 60 }
            }));
        });
    }

    function clearFloatOpen() {
        if (!sidebar) return;
        sidebar.querySelectorAll('.nav-item.is-float-open').forEach(function (item) {
            item.classList.remove('is-float-open');
        });
    }

    function positionFloatMenu(item) {
        const tree = item.querySelector(':scope > .nav-treeview');
        const trigger = item.querySelector(':scope > .nav-link');
        if (!tree || !trigger) return;

        const rect = trigger.getBoundingClientRect();
        const left = Math.round(rect.right - 2); /* slight overlap avoids hover gap */
        let top = Math.round(rect.top);
        tree.style.left = left + 'px';
        tree.style.top = top + 'px';

        /* Keep floating panel inside viewport */
        requestAnimationFrame(function () {
            const panel = tree.getBoundingClientRect();
            const maxBottom = window.innerHeight - 12;
            if (panel.bottom > maxBottom) {
                top = Math.max(12, maxBottom - panel.height);
                tree.style.top = top + 'px';
            }
            if (panel.right > window.innerWidth - 8) {
                tree.style.left = Math.max(8, window.innerWidth - panel.width - 8) + 'px';
            }
        });
    }

    function openFloatMenu(item) {
        if (!isIconRail()) return;
        clearFloatOpen();
        item.classList.add('is-float-open');
        positionFloatMenu(item);
    }

    let lastTooltipMode = null;

    function syncChrome() {
        const expanded = isExpanded();
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
        if (backdrop) {
            const showBackdrop = isOverlayMode() && body.classList.contains('sidebar-open');
            backdrop.setAttribute('aria-hidden', showBackdrop ? 'false' : 'true');
        }
        if (!isIconRail()) {
            clearFloatOpen();
        }
        const tipMode = isIconRail() ? 'rail' : 'off';
        if (tipMode !== lastTooltipMode) {
            lastTooltipMode = tipMode;
            syncTooltips();
        }
    }

    function applyResponsiveDefaults() {
        if (isOverlayMode()) {
            body.classList.add('sidebar-collapse');
            body.classList.remove('sidebar-open');
        } else {
            body.classList.remove('sidebar-open');
            if (userDesktopPreference === 'collapsed') {
                body.classList.add('sidebar-collapse');
            } else if (userDesktopPreference === 'expanded') {
                body.classList.remove('sidebar-collapse');
            } else {
                body.classList.remove('sidebar-collapse');
            }
        }
        clearFloatOpen();
        syncChrome();
    }

    const observer = new MutationObserver(function () {
        syncChrome();
    });
    observer.observe(body, { attributes: true, attributeFilter: ['class'] });

    toggle?.addEventListener('click', function (e) {
        if (isOverlayMode()) {
            e.preventDefault();
            e.stopPropagation();
            pushMenu('toggle');
            syncChrome();
            return;
        }

        window.setTimeout(function () {
            userDesktopPreference = body.classList.contains('sidebar-collapse')
                ? 'collapsed'
                : 'expanded';
            syncChrome();
        }, 0);
    });

    backdrop?.addEventListener('click', function () {
        pushMenu('collapse');
        syncChrome();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOverlayMode() && body.classList.contains('sidebar-open')) {
            pushMenu('collapse');
            syncChrome();
        }
    });

    sidebar?.addEventListener('click', function (e) {
        if (!isOverlayMode()) return;
        const link = e.target.closest('a.nav-link[href]');
        if (!link) return;
        const href = link.getAttribute('href') || '';
        if (!href || href === '#' || href.startsWith('javascript:')) return;
        setTimeout(function () {
            pushMenu('collapse');
            syncChrome();
        }, 80);
    });

    /* Floating submenu for collapsed desktop rail */
    sidebar?.addEventListener('mouseover', function (e) {
        if (!isIconRail()) return;
        const item = e.target.closest('.nav-sidebar > .nav-item');
        if (!item || !item.querySelector(':scope > .nav-treeview')) return;
        window.clearTimeout(floatCloseTimer);
        openFloatMenu(item);
    });

    sidebar?.addEventListener('mouseleave', function () {
        if (!isIconRail()) return;
        floatCloseTimer = window.setTimeout(clearFloatOpen, 260);
    });

    sidebar?.addEventListener('focusin', function (e) {
        if (!isIconRail()) return;
        const item = e.target.closest('.nav-sidebar > .nav-item');
        if (!item || !item.querySelector(':scope > .nav-treeview')) return;
        window.clearTimeout(floatCloseTimer);
        openFloatMenu(item);
    });

    sidebar?.addEventListener('click', function (e) {
        if (!isIconRail()) return;
        const trigger = e.target.closest('.nav-sidebar > .nav-item > .nav-link[href="#"]');
        if (!trigger) return;
        const item = trigger.parentElement;
        if (!item || !item.querySelector(':scope > .nav-treeview')) return;
        e.preventDefault();
        if (item.classList.contains('is-float-open')) {
            item.classList.remove('is-float-open');
        } else {
            openFloatMenu(item);
        }
    });

    window.addEventListener('scroll', function () {
        if (!isIconRail()) return;
        const open = sidebar?.querySelector('.nav-item.is-float-open');
        if (open) positionFloatMenu(open);
    }, true);

    window.addEventListener('resize', function () {
        if (!isIconRail()) return;
        const open = sidebar?.querySelector('.nav-item.is-float-open');
        if (open) positionFloatMenu(open);
    });

    function onViewportChange() {
        applyResponsiveDefaults();
    }

    if (typeof mqOverlay.addEventListener === 'function') {
        mqOverlay.addEventListener('change', onViewportChange);
    } else if (typeof mqOverlay.addListener === 'function') {
        mqOverlay.addListener(onViewportChange);
    }

    applyResponsiveDefaults();
})();
</script>
<script src="{{ asset('js/page-header-shortcuts.js') }}" defer></script>
@auth
<script>
(function () {
    var url = @json(route('presence.heartbeat'));
    var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!url || !token) return;

    function ping() {
        if (document.visibilityState === 'hidden') return;
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }).catch(function () { /* non-blocking */ });
    }

    setInterval(ping, 120000);
})();
</script>
@endauth
@stack('scripts')
</body>
</html>
