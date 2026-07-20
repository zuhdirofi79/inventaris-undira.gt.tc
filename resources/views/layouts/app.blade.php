<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventaris UNDIRA')</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="{{ asset('css/undira.css') }}">
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-dark navbar-undira">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="{{ route('dashboard') }}" class="nav-link">Dashboard</a>
            </li>
        </ul>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                <a class="nav-link" href="#" id="darkModeToggle" title="Mode Gelap">
                    <i class="fas fa-moon"></i>
                </a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link" data-bs-toggle="dropdown" href="#" id="notificationDropdown">
                    <i class="fas fa-bell"></i>
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
            <li class="nav-item">
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="nav-link btn btn-link text-white border-0">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </button>
                </form>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4 sidebar-dark-undira">
        <a href="{{ route('dashboard') }}" class="brand-link brand-link-undira undira-brand-link">
            <div class="undira-logo-container">
                <img
                    src="{{ asset('images/LOGO UNDIRA.png') }}"
                    alt="Logo Universitas Dian Nusantara"
                    class="undira-brand-logo"
                >
            </div>
            <span class="brand-text font-weight-light">Inventaris UNDIRA</span>
        </a>

        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex user-panel-undira">
                <div class="image">
                    <div class="img-circle elevation-2 bg-undira-green d-flex align-items-center justify-content-center" style="width:34px;height:34px;">
                        <i class="fas fa-user text-white"></i>
                    </div>
                </div>
                <div class="info ms-2">
                    <a href="#" class="d-block text-white">{{ auth()->user()->name }}</a>
                    <small class="text-muted">
                        <span class="status-indicator"></span> Online
                        @if(auth()->user()->roles->isNotEmpty())
                            &middot; {{ auth()->user()->roles->first()->name }}
                        @endif
                    </small>
                </div>
            </div>

            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    @can('location.view')
                    <li class="nav-item">
                        <a href="{{ route('campuses.index') }}" class="nav-link {{ request()->routeIs('campuses.*','buildings.*','floors.*','rooms.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-map-marked-alt"></i>
                            <p>Jelajahi Lokasi</p>
                        </a>
                    </li>
                    @endcan

                    @can('inventory.view')
                    <li class="nav-item">
                        <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.index','inventory.campus','inventory.categories','inventory.category','inventory.items','inventory.group-units','inventory.show','inventory.create','inventory.edit','inventory.trash','inventory.import.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-boxes"></i>
                            <p>Data Inventaris</p>
                        </a>
                    </li>
                    @endcan

                    @if(auth()->user()->can('category.manage') || auth()->user()->can('unit.manage') || auth()->user()->can('procurement.manage') || auth()->user()->can('supplier.manage') || auth()->user()->can('vendor.manage') || auth()->user()->can('location.manage'))
                    <li class="nav-item {{ request()->routeIs('categories.*','sub-categories.*','suppliers.*','vendors.*','org-units.*','procurement-types.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-database"></i>
                            <p>Master Data <i class="fas fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @can('category.manage')
                            <li class="nav-item">
                                <a href="{{ route('categories.index', ['tab' => 'konfigurasi']) }}"
                                   class="nav-link {{ request()->routeIs('master-configurations.*') || request()->routeIs('sub-categories.*') || (request()->routeIs('categories.*') && ! in_array(request('tab'), ['units', 'pengadaan', 'lokasi', 'klasifikasi'], true)) ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Kategori</p>
                                </a>
                            </li>
                            @endcan
                            @canany(['category.manage', 'unit.manage', 'procurement.manage', 'location.manage', 'supplier.manage', 'vendor.manage'])
                            <li class="nav-item">
                                <a href="{{ route('categories.index', ['tab' => 'lokasi']) }}"
                                   class="nav-link {{ request()->routeIs('categories.*') && in_array(request('tab'), ['units', 'pengadaan', 'lokasi', 'klasifikasi'], true) ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Hub Master Data</p>
                                </a>
                            </li>
                            @endcanany
                            @can('supplier.manage')
                            <li class="nav-item">
                                <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Supplier</p>
                                </a>
                            </li>
                            @endcan
                            @can('vendor.manage')
                            <li class="nav-item">
                                <a href="{{ route('vendors.index') }}" class="nav-link {{ request()->routeIs('vendors.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Vendor</p>
                                </a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    @if(auth()->user()->can('borrowing.view') || auth()->user()->can('maintenance.view') || auth()->user()->can('stock-opname.view') || auth()->user()->can('mutation.view') || auth()->user()->can('deletion-approval.view') || auth()->user()->can('inventory.view'))
                    <li class="nav-item {{ request()->routeIs('borrowings.*','maintenances.*','stock-opnames.*','mutations.*','deletion-approvals.*','scanner.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-cogs"></i>
                            <p>Operasi <i class="fas fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @can('inventory.view')
                            <li class="nav-item">
                                <a href="{{ route('scanner.index') }}" class="nav-link {{ request()->routeIs('scanner.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Scan Barcode</p>
                                </a>
                            </li>
                            @endcan
                            @can('borrowing.view')
                            <li class="nav-item">
                                <a href="{{ route('borrowings.index') }}" class="nav-link {{ request()->routeIs('borrowings.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Peminjaman</p>
                                </a>
                            </li>
                            @endcan
                            @can('maintenance.view')
                            <li class="nav-item">
                                <a href="{{ route('maintenances.index') }}" class="nav-link {{ request()->routeIs('maintenances.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Maintenance</p>
                                </a>
                            </li>
                            @endcan
                            @can('stock-opname.view')
                            <li class="nav-item">
                                <a href="{{ route('stock-opnames.index') }}" class="nav-link {{ request()->routeIs('stock-opnames.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Stock Opname</p>
                                </a>
                            </li>
                            @endcan
                            @can('mutation.view')
                            <li class="nav-item">
                                <a href="{{ route('mutations.index') }}" class="nav-link {{ request()->routeIs('mutations.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Mutasi</p>
                                </a>
                            </li>
                            @endcan
                            @can('deletion-approval.view')
                            <li class="nav-item">
                                <a href="{{ route('deletion-approvals.index') }}" class="nav-link {{ request()->routeIs('deletion-approvals.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Persetujuan Hapus</p>
                                </a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    @can('report.view')
                    <li class="nav-item">
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>Laporan</p>
                        </a>
                    </li>
                    @endcan

                    @canany(['location.manage', 'user.manage', 'settings.manage', 'backup.manage', 'audit-log.view'])
                    <li class="nav-item {{ request()->routeIs('admin.*','audit-logs.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-shield"></i>
                            <p>Admin <i class="fas fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @can('location.manage')
                            <li class="nav-item">
                                <a href="{{ route('admin.locations.campuses.index') }}" class="nav-link {{ request()->routeIs('admin.locations.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Kelola Lokasi</p>
                                </a>
                            </li>
                            @endcan
                            @can('user.manage')
                            <li class="nav-item">
                                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Users</p>
                                </a>
                            </li>
                            @endcan
                            @can('audit-log.view')
                            <li class="nav-item">
                                <a href="{{ route('audit-logs.index') }}" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Audit Log</p>
                                </a>
                            </li>
                            @endcan
                            @can('settings.manage')
                            <li class="nav-item">
                                <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
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

<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 text-muted">Memuat...</p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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

function showLoading() { $('#loadingOverlay').addClass('active'); }
function hideLoading() { $('#loadingOverlay').removeClass('active'); }

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
        title: 'Konfirmasi',
        text: form.getAttribute('data-confirm') || 'Yakin ingin melanjutkan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2E7D32',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });
});
</script>
@stack('scripts')
</body>
</html>
