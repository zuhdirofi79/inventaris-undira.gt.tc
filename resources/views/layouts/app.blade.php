<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventaris UNDIRA')</title>

    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-undira.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="{{ asset('css/undira.css') }}">
    <link rel="stylesheet" href="{{ asset('css/undira-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/undira-loading.css') }}">
    <link rel="stylesheet" href="{{ asset('css/undira-modals.css') }}">
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<script>
/* Apply layout mode before first paint to avoid expanded-sidebar flash on tablet. */
(function () {
    var w = window.innerWidth || document.documentElement.clientWidth || 1200;
    if (w < 1200) {
        document.body.classList.add('sidebar-collapse');
    }
    if (w < 768) {
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
                <a href="{{ route('dashboard') }}" class="nav-link app-identity">
                    <span class="app-identity-title">Inventaris UNDIRA</span>
                    <span class="app-identity-subtitle">Universitas Dian Nusantara</span>
                </a>
            </li>
        </ul>
        <ul class="navbar-nav ms-auto align-items-center flex-nowrap">
            <li class="nav-item">
                <a class="nav-link" href="#" id="darkModeToggle" title="Mode Gelap" aria-label="Alihkan mode gelap">
                    <i class="fas fa-moon" aria-hidden="true"></i>
                </a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link" data-bs-toggle="dropdown" href="#" id="notificationDropdown" aria-label="Notifikasi">
                    <i class="fas fa-bell" aria-hidden="true"></i>
                    <span class="badge badge-warning navbar-badge" id="notificationCount" style="display:none;">0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" id="notificationDropdownMenu">
                    <span class="dropdown-item dropdown-header">Notifikasi</span>
                    <div class="dropdown-divider"></div>
                    <div id="notificationList"></div>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('notifications.index') }}" class="dropdown-item dropdown-footer">Lihat Semua</a>
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

    <aside class="main-sidebar sidebar-dark-primary elevation-4 sidebar-dark-undira" id="main-sidebar" aria-label="Navigasi utama">
        <a href="{{ route('dashboard') }}" class="brand-link undira-sidebar-brand" title="Inventaris UNDIRA">
            <span class="undira-sidebar-logo-wrap">
                <img
                    src="{{ asset('images/LOGO UNDIRA.png') }}"
                    alt="Logo Universitas Dian Nusantara"
                    class="undira-sidebar-logo"
                    decoding="async"
                >
            </span>
            <span class="undira-brand-mark" aria-hidden="true">UN</span>
        </a>

        <div class="sidebar">
            <div class="user-panel user-panel-undira d-flex">
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

            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-header">UTAMA</li>

                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    @can('location.view')
                    <li class="nav-item">
                        <a href="{{ route('campuses.index') }}"
                           class="nav-link {{ request()->routeIs('campuses.*', 'buildings.*', 'floors.*', 'rooms.*') && ! request()->routeIs('admin.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-map-marked-alt"></i>
                            <p>Jelajahi Lokasi</p>
                        </a>
                    </li>
                    @endcan

                    <li class="nav-header">DATA</li>

                    @if(auth()->user()->can('inventory.view') || auth()->user()->can('category.manage'))
                    <li class="nav-item {{ request()->routeIs('inventory.*') || request()->routeIs('master-configurations.*') || (request()->routeIs('categories.*') && request('tab', 'konfigurasi') === 'konfigurasi') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-boxes"></i>
                            <p>Inventaris <i class="fas fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @can('inventory.view')
                            <li class="nav-item">
                                <a href="{{ route('inventory.index') }}"
                                   class="nav-link {{ request()->routeIs('inventory.index','inventory.campus','inventory.categories','inventory.category','inventory.items','inventory.group-units','inventory.show','inventory.create','inventory.edit','inventory.trash','inventory.import.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-list"></i>
                                    <p>Data Inventaris</p>
                                </a>
                            </li>
                            @endcan
                            @can('category.manage')
                            <li class="nav-item">
                                <a href="{{ route('categories.index', ['tab' => 'konfigurasi']) }}"
                                   class="nav-link {{ request()->routeIs('master-configurations.*') || (request()->routeIs('categories.*') && request('tab', 'konfigurasi') === 'konfigurasi') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-sliders-h"></i>
                                    <p>Konfigurasi Inventaris</p>
                                </a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    @if(auth()->user()->can('category.manage') || auth()->user()->can('unit.manage') || auth()->user()->can('procurement.manage') || auth()->user()->can('supplier.manage') || auth()->user()->can('vendor.manage') || auth()->user()->can('location.manage'))
                    <li class="nav-item {{ request()->routeIs('categories.*','sub-categories.*','suppliers.*','vendors.*','org-units.*','procurement-types.*','admin.locations.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-database"></i>
                            <p>Master Data <i class="fas fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @can('location.manage')
                            <li class="nav-item">
                                <a href="{{ route('admin.locations.campuses.index') }}"
                                   class="nav-link {{ request()->routeIs('admin.locations.campuses.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-university"></i>
                                    <p>Kampus</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.locations.campuses.index') }}"
                                   class="nav-link {{ request()->routeIs('admin.locations.buildings.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-building"></i>
                                    <p>Gedung</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.locations.campuses.index') }}"
                                   class="nav-link {{ request()->routeIs('admin.locations.floors.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-layer-group"></i>
                                    <p>Lantai</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.locations.campuses.index') }}"
                                   class="nav-link {{ request()->routeIs('admin.locations.rooms.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-door-open"></i>
                                    <p>Ruangan</p>
                                </a>
                            </li>
                            @endcan
                            @can('category.manage')
                            <li class="nav-item">
                                <a href="{{ route('categories.index', ['tab' => 'klasifikasi']) }}"
                                   class="nav-link {{ request()->routeIs('categories.*') && request('tab') === 'klasifikasi' ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-tags"></i>
                                    <p>Kategori</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('categories.index', ['tab' => 'klasifikasi']) }}"
                                   class="nav-link {{ request()->routeIs('sub-categories.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-tag"></i>
                                    <p>Sub Kategori</p>
                                </a>
                            </li>
                            @endcan
                            @can('supplier.manage')
                            <li class="nav-item">
                                <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-truck"></i>
                                    <p>Supplier</p>
                                </a>
                            </li>
                            @endcan
                            @can('vendor.manage')
                            <li class="nav-item">
                                <a href="{{ route('vendors.index') }}" class="nav-link {{ request()->routeIs('vendors.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-store"></i>
                                    <p>Vendor</p>
                                </a>
                            </li>
                            @endcan
                            @canany(['category.manage', 'unit.manage', 'procurement.manage', 'location.manage', 'supplier.manage', 'vendor.manage'])
                            <li class="nav-item">
                                <a href="{{ route('categories.index', ['tab' => 'lokasi']) }}"
                                   class="nav-link {{ request()->routeIs('categories.*') && request('tab') === 'lokasi' ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-sitemap"></i>
                                    <p>Hub Master Data</p>
                                </a>
                            </li>
                            @endcanany
                        </ul>
                    </li>
                    @endif

                    <li class="nav-header">OPERASI</li>

                    @if(auth()->user()->can('borrowing.view') || auth()->user()->can('maintenance.view') || auth()->user()->can('stock-opname.view') || auth()->user()->can('mutation.view') || auth()->user()->can('deletion-approval.view') || auth()->user()->can('inventory.view') || auth()->user()->can('procurement.manage'))
                    <li class="nav-item {{ request()->routeIs('borrowings.*','maintenances.*','stock-opnames.*','mutations.*','deletion-approvals.*','scanner.*') || (request()->routeIs('categories.*') && request('tab') === 'pengadaan') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-briefcase"></i>
                            <p>Operasional <i class="fas fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @can('inventory.view')
                            <li class="nav-item">
                                <a href="{{ route('scanner.index') }}" class="nav-link {{ request()->routeIs('scanner.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-barcode"></i>
                                    <p>Scan Barcode</p>
                                </a>
                            </li>
                            @endcan
                            @can('borrowing.view')
                            <li class="nav-item">
                                <a href="{{ route('borrowings.index') }}" class="nav-link {{ request()->routeIs('borrowings.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-handshake"></i>
                                    <p>Peminjaman</p>
                                </a>
                            </li>
                            @endcan
                            @can('maintenance.view')
                            <li class="nav-item">
                                <a href="{{ route('maintenances.index') }}" class="nav-link {{ request()->routeIs('maintenances.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-tools"></i>
                                    <p>Maintenance</p>
                                </a>
                            </li>
                            @endcan
                            @can('mutation.view')
                            <li class="nav-item">
                                <a href="{{ route('mutations.index') }}" class="nav-link {{ request()->routeIs('mutations.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-exchange-alt"></i>
                                    <p>Mutasi</p>
                                </a>
                            </li>
                            @endcan
                            @can('procurement.manage')
                            <li class="nav-item">
                                <a href="{{ route('categories.index', ['tab' => 'pengadaan']) }}"
                                   class="nav-link {{ request()->routeIs('categories.*') && request('tab') === 'pengadaan' ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-shopping-cart"></i>
                                    <p>Pengadaan</p>
                                </a>
                            </li>
                            @endcan
                            @can('stock-opname.view')
                            <li class="nav-item">
                                <a href="{{ route('stock-opnames.index') }}" class="nav-link {{ request()->routeIs('stock-opnames.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-clipboard-check"></i>
                                    <p>Stock Opname</p>
                                </a>
                            </li>
                            @endcan
                            @can('deletion-approval.view')
                            <li class="nav-item">
                                <a href="{{ route('deletion-approvals.index') }}" class="nav-link {{ request()->routeIs('deletion-approvals.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-trash-alt"></i>
                                    <p>Persetujuan Hapus</p>
                                </a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    <li class="nav-header">LAPORAN</li>

                    @can('report.view')
                    <li class="nav-item">
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>Laporan</p>
                        </a>
                    </li>
                    @endcan

                    <li class="nav-header">SISTEM</li>

                    @canany(['user.manage', 'settings.manage', 'backup.manage', 'audit-log.view'])
                    <li class="nav-item {{ request()->routeIs('admin.users.*','admin.settings.*','audit-logs.*','trash.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-shield"></i>
                            <p>Admin <i class="fas fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @role('super-admin')
                            <li class="nav-item">
                                <a href="{{ route('trash.index') }}" class="nav-link {{ request()->routeIs('trash.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-trash text-secondary"></i>
                                    <p>Trash</p>
                                </a>
                            </li>
                            @endrole
                            @can('user.manage')
                            <li class="nav-item">
                                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-users"></i>
                                    <p>Users</p>
                                </a>
                            </li>
                            @endcan
                            @can('audit-log.view')
                            <li class="nav-item">
                                <a href="{{ route('audit-logs.index') }}" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-history"></i>
                                    <p>Audit Log</p>
                                </a>
                            </li>
                            @endcan
                            @can('settings.manage')
                            <li class="nav-item">
                                <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-cog"></i>
                                    <p>Settings</p>
                                </a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endcanany
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                @hasSection('breadcrumb')
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-undira">
                        @yield('breadcrumb')
                    </ol>
                </nav>
                @endif
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div id="toast-container"></div>
                @include('components.alert')
                @yield('content')
            </div>
        </section>
    </div>

    <footer class="main-footer text-sm">
        <strong>&copy; {{ date('Y') }} Inventaris UNDIRA.</strong> Universitas Dian Nusantara.
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
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/undira-loading.js') }}"></script>
<script>
$.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
});

function showToast(message, type = 'success') {
    const bg = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
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

/* showLoading / hideLoading disediakan oleh undira-loading.js (overlay logo UNDIRA) */

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

// Notifications
function loadNotifications() {
    $.get('{{ route('notifications.unread') }}', function(data) {
        if (data.count > 0) {
            $('#notificationCount').text(data.count).show();
        } else {
            $('#notificationCount').hide();
        }
        let html = '';
        if (data.notifications.length === 0) {
            html = '<span class="dropdown-item text-muted">Tidak ada notifikasi baru</span>';
        } else {
            data.notifications.forEach(function(n) {
                const msg = n.data.message || n.data.title || 'Notifikasi baru';
                html += `<a href="{{ route('notifications.index') }}" class="dropdown-item"><i class="fas fa-bell me-2"></i>${msg}</a>`;
            });
        }
        $('#notificationList').html(html);
    });
}
@if(auth()->check())
loadNotifications();
setInterval(loadNotifications, 60000);
@endif

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
        if (result.isConfirmed) form.submit();
    });
});

// Sidebar / overlay sync (AdminLTE PushMenu) — three layout modes
(function () {
    const body = document.body;
    const toggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
    const mqMobile = window.matchMedia('(max-width: 767.98px)');
    const mqCompact = window.matchMedia('(max-width: 1199.98px)');
    let userDesktopPreference = null; // null | 'expanded' | 'collapsed'

    function pushMenu(action) {
        const $btn = window.jQuery ? window.jQuery('[data-widget="pushmenu"]') : null;
        if ($btn && $btn.length && typeof $btn.PushMenu === 'function') {
            $btn.PushMenu(action);
            return;
        }
        if (action === 'collapse') {
            body.classList.remove('sidebar-open');
            body.classList.add('sidebar-collapse');
        } else if (action === 'expand') {
            body.classList.remove('sidebar-collapse');
            if (mqMobile.matches) {
                body.classList.add('sidebar-open');
            }
        }
    }

    function isExpanded() {
        if (mqMobile.matches) {
            return body.classList.contains('sidebar-open');
        }
        return !body.classList.contains('sidebar-collapse');
    }

    function syncChrome() {
        const expanded = isExpanded();
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
        if (backdrop) {
            const showBackdrop = mqMobile.matches && body.classList.contains('sidebar-open');
            backdrop.setAttribute('aria-hidden', showBackdrop ? 'false' : 'true');
        }
    }

    function applyResponsiveDefaults(fromResize) {
        if (mqMobile.matches) {
            // Mobile: off-canvas closed by default
            body.classList.add('sidebar-collapse');
            if (!fromResize || !body.classList.contains('sidebar-open')) {
                body.classList.remove('sidebar-open');
            }
        } else if (mqCompact.matches) {
            // Tablet / small desktop: compact icon rail
            body.classList.remove('sidebar-open');
            body.classList.add('sidebar-collapse');
        } else {
            // Large desktop: restore preference or expand
            body.classList.remove('sidebar-open');
            if (userDesktopPreference === 'collapsed') {
                body.classList.add('sidebar-collapse');
            } else {
                body.classList.remove('sidebar-collapse');
            }
        }
        syncChrome();
    }

    const observer = new MutationObserver(syncChrome);
    observer.observe(body, { attributes: true, attributeFilter: ['class'] });

    toggle?.addEventListener('click', function () {
        // Capture desktop preference after AdminLTE toggles on next tick
        window.setTimeout(function () {
            if (!mqCompact.matches) {
                userDesktopPreference = body.classList.contains('sidebar-collapse')
                    ? 'collapsed'
                    : 'expanded';
            }
            syncChrome();
        }, 0);
    });

    backdrop?.addEventListener('click', function () {
        pushMenu('collapse');
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && mqMobile.matches && body.classList.contains('sidebar-open')) {
            pushMenu('collapse');
        }
    });

    document.getElementById('main-sidebar')?.addEventListener('click', function (e) {
        if (!mqMobile.matches) return;
        const link = e.target.closest('a.nav-link[href]');
        if (!link) return;
        const href = link.getAttribute('href') || '';
        if (!href || href === '#' || href.startsWith('javascript:')) return;
        setTimeout(function () { pushMenu('collapse'); }, 80);
    });

    function onViewportChange() {
        applyResponsiveDefaults(true);
    }

    if (typeof mqMobile.addEventListener === 'function') {
        mqMobile.addEventListener('change', onViewportChange);
        mqCompact.addEventListener('change', onViewportChange);
    } else if (typeof mqMobile.addListener === 'function') {
        mqMobile.addListener(onViewportChange);
        mqCompact.addListener(onViewportChange);
    }

    applyResponsiveDefaults(false);
})();
</script>
@stack('scripts')
</body>
</html>
