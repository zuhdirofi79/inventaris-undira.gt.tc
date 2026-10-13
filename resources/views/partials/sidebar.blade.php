{{-- Role-aware enterprise sidebar — UX/structure only (routes & permissions unchanged). --}}
@php
    use App\Support\SidebarMenu;

    $user = auth()->user();
    $isAdmin = SidebarMenu::isAdminPersona($user);
    $isStaff = SidebarMenu::isStaffPersona($user);
    $isTeknisi = SidebarMenu::isTeknisiPersona($user);
    $isViewer = SidebarMenu::isViewerPersona($user);
    $isSuperAdmin = SidebarMenu::isSuperAdmin($user);
    $isOperator = $isAdmin || $isStaff;
@endphp

<nav class="mt-2 undira-sidebar-nav" aria-label="Menu utama">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

        {{-- ========== DASHBOARD ========== --}}
        <li class="nav-header undira-nav-section">Dashboard</li>
        <li class="nav-item">
            @if($isTeknisi)
                <a href="{{ route('teknisi-dashboard.index') }}" class="nav-link {{ request()->routeIs('teknisi-dashboard.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <p>Dashboard</p>
                </a>
            @else
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') || request()->routeIs('dashboard.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <p>Dashboard</p>
                </a>
            @endif
        </li>

        {{-- ========== LOKASI ========== --}}
        @can('location.view')
            @unless($isTeknisi)
            <li class="nav-header undira-nav-section">Lokasi</li>
            <li class="nav-item">
                <a href="{{ route('campuses.index') }}"
                   class="nav-link {{ request()->routeIs('campuses.*', 'buildings.*', 'floors.*', 'rooms.*', 'locations.explore.*') && ! request()->routeIs('admin.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-map-marked-alt"></i>
                    <p>Jelajahi Lokasi</p>
                </a>
            </li>
            @endunless
        @endcan

        {{-- ========== INVENTARIS (flat — max 1 level) ========== --}}
        @can('inventory.view')
        <li class="nav-header undira-nav-section">Inventaris</li>
        @if($isViewer)
            <li class="nav-item">
                <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.*') && ! request()->routeIs('inventory.create') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-boxes"></i>
                    <p>Inventaris (Read Only)</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('scanner.index') }}" class="nav-link {{ request()->routeIs('scanner.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-qrcode"></i>
                    <p>Scan QR Inventaris</p>
                </a>
            </li>
        @elseif($isTeknisi)
            <li class="nav-item">
                <a href="{{ route('inventory.index') }}"
                   class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-boxes"></i>
                    <p>Inventaris (Read Only)</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('scanner.index') }}" class="nav-link {{ request()->routeIs('scanner.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-qrcode"></i>
                    <p>Scan QR Inventaris</p>
                </a>
            </li>
        @else
            <li class="nav-item">
                <a href="{{ route('inventory.index') }}"
                   class="nav-link {{ request()->routeIs('inventory.index','inventory.campus','inventory.categories','inventory.category') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-th-large"></i>
                    <p>Dashboard Inventaris</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('inventory.items') }}"
                   class="nav-link {{ request()->routeIs('inventory.items','inventory.show','inventory.group-units','inventory.trash') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-list"></i>
                    <p>Data Inventaris</p>
                </a>
            </li>
            @can('inventory.create')
            <li class="nav-item">
                <a href="{{ route('inventory.create') }}" class="nav-link {{ request()->routeIs('inventory.create') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-plus"></i>
                    <p>Tambah Inventaris</p>
                </a>
            </li>
            @endcan
            <li class="nav-item">
                <a href="{{ route('scanner.index') }}" class="nav-link {{ request()->routeIs('scanner.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-qrcode"></i>
                    <p>Scan QR Inventaris</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('inventory.items') }}#mass-labels"
                   class="nav-link"
                   data-no-loader="1">
                    <i class="nav-icon fas fa-tags"></i>
                    <p>Cetak Label QR</p>
                </a>
            </li>
        @endif
        @endcan

        {{-- ========== OPERASIONAL ========== --}}
        @if($isViewer)
            @canany(['borrowing.view', 'borrowing.create', 'room-borrowing.view', 'room-borrowing.create', 'vehicle-borrowing.view', 'vehicle-borrowing.create'])
            <li class="nav-header undira-nav-section">Operasional</li>
            @include('partials.sidebar-borrowing')
            @endcanany

            <li class="nav-header undira-nav-section">Akun</li>
            <li class="nav-item">
                <a href="#sidebar-user-panel" class="nav-link" data-no-loader="1">
                    <i class="nav-icon fas fa-user"></i>
                    <p>Profil</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-bell"></i>
                    <p>Notifikasi</p>
                </a>
            </li>

        @elseif($isTeknisi)
            @can('maintenance.view')
            <li class="nav-header undira-nav-section">Operasional</li>
            <li class="nav-item {{ request()->routeIs('maintenances.*','maintenance-schedules.*','teknisi-dashboard.*') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-tools"></i>
                    <p>Maintenance</p>
                    <i class="fas fa-angle-left right" aria-hidden="true"></i>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="{{ route('teknisi-dashboard.index') }}" class="nav-link {{ request()->routeIs('teknisi-dashboard.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard Maintenance</p>
                        </a>
                    </li>
                    @can('maintenance.create')
                    <li class="nav-item">
                        <a href="{{ route('maintenances.scan') }}" class="nav-link {{ request()->routeIs('maintenances.scan*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-qrcode"></i>
                            <p>Scan QR Maintenance</p>
                        </a>
                    </li>
                    @endcan
                    <li class="nav-item">
                        <a href="{{ route('maintenances.index') }}"
                           class="nav-link {{ request()->routeIs('maintenances.*') && ! request()->routeIs('maintenances.scan*') && request('status') !== 'completed' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Work Order Saya</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('maintenance-schedules.index') }}" class="nav-link {{ request()->routeIs('maintenance-schedules.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-check"></i>
                            <p>Jadwal Preventif</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('maintenances.index', ['status' => 'completed']) }}"
                           class="nav-link {{ request()->routeIs('maintenances.*') && request('status') === 'completed' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-history"></i>
                            <p>Riwayat Maintenance</p>
                        </a>
                    </li>
                </ul>
            </li>
            @endcan

            @can('maintenance.view')
            <li class="nav-header undira-nav-section">Laporan</li>
            <li class="nav-item">
                <a href="{{ route('maintenance-reports.index') }}" class="nav-link {{ request()->routeIs('maintenance-reports.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-file-medical"></i>
                    <p>Laporan Maintenance</p>
                </a>
            </li>
            @endcan

            <li class="nav-header undira-nav-section">Akun</li>
            <li class="nav-item">
                <a href="#sidebar-user-panel" class="nav-link" data-no-loader="1">
                    <i class="nav-icon fas fa-user"></i>
                    <p>Profil</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-bell"></i>
                    <p>Notifikasi</p>
                </a>
            </li>

        @else
            {{-- Admin / Staff / Super Admin --}}
            @if(
                $user->can('borrowing.view') || $user->can('maintenance.view') || $user->can('stock-opname.view')
                || $user->can('mutation.view') || $user->can('deletion-approval.view')
            )
            <li class="nav-header undira-nav-section">Operasional</li>

            {{-- Peminjaman --}}
            @canany(['borrowing.view', 'borrowing.create', 'borrowing.approve', 'room-borrowing.view', 'room-borrowing.create', 'room-borrowing.approve', 'vehicle-borrowing.view', 'vehicle-borrowing.create', 'vehicle-borrowing.approve', 'vehicle-fleet.manage'])
            @include('partials.sidebar-borrowing')
            @endcanany

            {{-- Maintenance --}}
            @can('maintenance.view')
            <li class="nav-item {{ request()->routeIs('maintenances.*','maintenance-schedules.*','maintenance-dashboard.*','maintenance-approvals.*','inventory.damaged.*') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-tools"></i>
                    <p>Maintenance</p>
                    <i class="fas fa-angle-left right" aria-hidden="true"></i>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="{{ route('maintenance-dashboard.index') }}" class="nav-link {{ request()->routeIs('maintenance-dashboard.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard Maintenance</p>
                        </a>
                    </li>
                    @can('maintenance.create')
                    <li class="nav-item">
                        <a href="{{ route('maintenances.scan') }}" class="nav-link {{ request()->routeIs('maintenances.scan*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-qrcode"></i>
                            <p>Scan QR Maintenance</p>
                        </a>
                    </li>
                    @endcan
                    <li class="nav-item">
                        <a href="{{ route('maintenances.index') }}"
                           class="nav-link {{ request()->routeIs('maintenances.*') && ! request()->routeIs('maintenances.scan*') && ! request()->routeIs('maintenance-schedules.*','maintenance-dashboard.*','maintenance-approvals.*','maintenance-reports.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Work Order</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('maintenance-schedules.index') }}" class="nav-link {{ request()->routeIs('maintenance-schedules.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-check"></i>
                            <p>Jadwal Preventif</p>
                        </a>
                    </li>
                    @can('maintenance.approve')
                    <li class="nav-item">
                        <a href="{{ route('maintenance-approvals.index') }}" class="nav-link {{ request()->routeIs('maintenance-approvals.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-clipboard-check"></i>
                            <p>Approval Maintenance</p>
                        </a>
                    </li>
                    @endcan
                    @can('inventory.view')
                    <li class="nav-item">
                        <a href="{{ route('inventory.damaged.index') }}" class="nav-link {{ request()->routeIs('inventory.damaged.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-exclamation-triangle"></i>
                            <p>Barang Rusak</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endcan

            {{-- Operasional Aset: transaksi aset non-peminjaman / non-maintenance --}}
            @if($user->can('mutation.view') || $user->can('stock-opname.view') || $user->can('deletion-approval.view'))
            <li class="nav-item {{ request()->routeIs('mutations.*','stock-opnames.*','deletion-approvals.*') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-dolly"></i>
                    <p>Operasional Aset</p>
                    <i class="fas fa-angle-left right" aria-hidden="true"></i>
                </a>
                <ul class="nav nav-treeview">
                    @can('mutation.view')
                    <li class="nav-item">
                        <a href="{{ route('mutations.index') }}" class="nav-link {{ request()->routeIs('mutations.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-exchange-alt"></i>
                            <p>Mutasi</p>
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
            @endif

            {{-- ========== LAPORAN (flat) ========== --}}
            @if($user->can('report.view') || $user->can('maintenance.view'))
            <li class="nav-header undira-nav-section">Laporan</li>
            @can('report.view')
            <li class="nav-item">
                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.index') || (request()->routeIs('reports.show') && request()->route('type') !== 'dipinjam') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-file-invoice"></i>
                    <p>Laporan Inventaris</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('reports.show', 'dipinjam') }}" class="nav-link {{ request()->routeIs('reports.*') && request()->route('type') === 'dipinjam' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-file-signature"></i>
                    <p>Laporan Peminjaman</p>
                </a>
            </li>
            @endcan
            @can('maintenance.view')
            <li class="nav-item">
                <a href="{{ route('maintenance-reports.index') }}" class="nav-link {{ request()->routeIs('maintenance-reports.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-file-medical"></i>
                    <p>Laporan Maintenance</p>
                </a>
            </li>
            @endcan
            @endif

            {{-- ========== MASTER DATA (flat + Hub paling bawah) ========== --}}
            @if($isOperator && (
                $user->can('location.manage') || $user->can('category.manage') || $user->can('unit.manage')
                || $user->can('supplier.manage') || $user->can('vendor.manage') || $user->can('procurement.manage')
            ))
            <li class="nav-header undira-nav-section">Master Data</li>
            @can('location.manage')
            <li class="nav-item">
                <a href="{{ route('admin.locations.campuses.index') }}"
                   class="nav-link {{ request()->routeIs('admin.locations.campuses.*') && ! request()->routeIs('admin.locations.buildings.*','admin.locations.floors.*','admin.locations.rooms.*') ? 'active' : '' }}">
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
            @can('unit.manage')
            <li class="nav-item">
                <a href="{{ route('categories.index', ['tab' => 'units']) }}"
                   class="nav-link {{ request()->routeIs('org-units.*') || (request()->routeIs('categories.*') && request('tab') === 'units') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-users"></i>
                    <p>Unit / Divisi</p>
                </a>
            </li>
            @endcan
            @can('category.manage')
            <li class="nav-item">
                <a href="{{ route('categories.index', ['tab' => 'klasifikasi']) }}"
                   class="nav-link {{ (request()->routeIs('categories.*') && request('tab') === 'klasifikasi') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-folder"></i>
                    <p>Kategori</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('categories.index', ['tab' => 'klasifikasi']) }}"
                   class="nav-link {{ request()->routeIs('sub-categories.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-folder-open"></i>
                    <p>Sub Kategori</p>
                </a>
            </li>
            @endcan
            @can('procurement.manage')
            <li class="nav-item">
                <a href="{{ route('categories.index', ['tab' => 'pengadaan']) }}"
                   class="nav-link {{ request()->routeIs('categories.*') && request('tab') === 'pengadaan' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-file-invoice-dollar"></i>
                    <p>Jenis Pengadaan</p>
                </a>
            </li>
            @endcan
            @can('vendor.manage')
            <li class="nav-item">
                <a href="{{ route('vendors.index') }}" class="nav-link {{ request()->routeIs('vendors.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-industry"></i>
                    <p>Vendor</p>
                </a>
            </li>
            @endcan
            @can('supplier.manage')
            <li class="nav-item">
                <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-truck-loading"></i>
                    <p>Supplier</p>
                </a>
            </li>
            @endcan
            <li class="nav-item">
                <a href="{{ route('categories.index', ['tab' => 'lokasi', 'section' => 'status']) }}#section-status-inventaris"
                   class="nav-link {{ request()->routeIs('categories.*') && request('tab') === 'lokasi' && request('section') === 'status' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-toggle-on"></i>
                    <p>Status Inventaris</p>
                </a>
            </li>
            <li class="nav-item undira-nav-hub">
                <a href="{{ route('categories.index', ['tab' => 'lokasi']) }}"
                   class="nav-link {{ request()->routeIs('categories.*') && request('tab') === 'lokasi' && request('section') !== 'status' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-th"></i>
                    <p>Hub Master Data</p>
                </a>
            </li>
            @endif

            {{-- ========== SISTEM (flat) ========== --}}
            @canany(['user.manage', 'settings.manage', 'backup.manage', 'audit-log.view', 'trash.view'])
            <li class="nav-header undira-nav-section">Sistem</li>
            @can('user.manage')
            <li class="nav-item">
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-user"></i>
                    <p>User Management</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.users.index') }}" class="nav-link">
                    <i class="nav-icon fas fa-key"></i>
                    <p>Role &amp; Permission</p>
                </a>
            </li>
            @endcan
            @can('audit-log.view')
            <li class="nav-item">
                <a href="{{ route('audit-logs.index') }}" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-scroll"></i>
                    <p>Audit Log</p>
                </a>
            </li>
            @endcan
            @can('trash.view')
            <li class="nav-item">
                <a href="{{ route('trash.index') }}" class="nav-link {{ request()->routeIs('trash.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-trash"></i>
                    <p>Trash</p>
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
            @endcanany
        @endif
    </ul>
</nav>
