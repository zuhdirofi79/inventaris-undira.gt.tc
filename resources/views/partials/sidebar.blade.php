{{-- Role-aware sidebar UX only — routes / permissions / workflows unchanged. --}}
@php
    use App\Support\SidebarMenu;
    use App\Enums\BorrowingStatus;

    $user = auth()->user();
    $isAdmin = SidebarMenu::isAdminPersona($user);
    $isStaff = SidebarMenu::isStaffPersona($user);
    $isTeknisi = SidebarMenu::isTeknisiPersona($user);
    $isViewer = SidebarMenu::isViewerPersona($user);
    $isSuperAdmin = SidebarMenu::isSuperAdmin($user);
    $isOperator = $isAdmin || $isStaff;
@endphp

<nav class="mt-2" aria-label="Menu utama">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

        {{-- ========== UTAMA ========== --}}
        <li class="nav-header">UTAMA</li>

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

        @can('location.view')
            @unless($isTeknisi)
            <li class="nav-item">
                <a href="{{ route('campuses.index') }}"
                   class="nav-link {{ request()->routeIs('campuses.*', 'buildings.*', 'floors.*', 'rooms.*', 'locations.explore.*') && ! request()->routeIs('admin.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-map-marked-alt"></i>
                    <p>Jelajahi Lokasi</p>
                </a>
            </li>
            @endunless
        @endcan

        {{-- ========== INVENTARIS (modul utama — tetap ada untuk semua role berizin) ========== --}}
        @can('inventory.view')
        <li class="nav-header">INVENTARIS</li>
        <li class="nav-item {{ request()->routeIs('inventory.*') || request()->routeIs('master-configurations.*') || (request()->routeIs('categories.*') && request('tab', '') === 'konfigurasi') ? 'menu-open' : '' }}">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-boxes"></i>
                <p>Inventaris <i class="fas fa-angle-left right"></i></p>
            </a>
            <ul class="nav nav-treeview">
                @if($isViewer)
                    <li class="nav-item">
                        <a href="{{ route('inventory.index') }}"
                           class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-eye"></i>
                            <p>Lihat Inventaris (Read Only)</p>
                        </a>
                    </li>
                @elseif($isTeknisi)
                    <li class="nav-item">
                        <a href="{{ route('inventory.index') }}"
                           class="nav-link {{ request()->routeIs('inventory.index','inventory.campus','inventory.categories','inventory.category') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-list"></i>
                            <p>Lihat Inventaris</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('inventory.items') }}"
                           class="nav-link {{ request()->routeIs('inventory.items','inventory.show','inventory.group-units') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-info-circle"></i>
                            <p>Detail Inventaris</p>
                        </a>
                    </li>
                @else
                    {{-- Super Admin / Admin / Staff --}}
                    <li class="nav-item">
                        <a href="{{ route('inventory.index') }}"
                           class="nav-link {{ request()->routeIs('inventory.index','inventory.campus','inventory.categories','inventory.category') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
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
                            <i class="nav-icon fas fa-plus-circle"></i>
                            <p>Tambah Inventaris</p>
                        </a>
                    </li>
                    @endcan
                    <li class="nav-item">
                        <a href="{{ route('inventory.items') }}#mass-labels"
                           class="nav-link {{ request()->routeIs('inventory.items') ? 'active' : '' }}"
                           data-no-loader="1">
                            <i class="nav-icon fas fa-qrcode"></i>
                            <p>Cetak Label QR</p>
                        </a>
                    </li>
                    @if($isSuperAdmin)
                        @canany(['category.manage', 'inventory.create'])
                        <li class="nav-item">
                            <a href="{{ route('categories.index', ['tab' => 'konfigurasi']) }}"
                               class="nav-link {{ request()->routeIs('master-configurations.*') || (request()->routeIs('categories.*') && request('tab') === 'konfigurasi') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-sliders-h"></i>
                                <p>Konfigurasi Inventaris</p>
                            </a>
                        </li>
                        @endcanany
                    @endif
                @endif
            </ul>
        </li>
        @endcan

        {{-- ========== MASTER DATA (operator only) ========== --}}
        @if($isOperator && (
            $user->can('location.manage') || $user->can('category.manage') || $user->can('unit.manage')
            || $user->can('supplier.manage') || $user->can('vendor.manage') || $user->can('procurement.manage')
        ))
        <li class="nav-header">MASTER DATA</li>
        <li class="nav-item {{ request()->routeIs('categories.*','sub-categories.*','suppliers.*','vendors.*','org-units.*','procurement-types.*','admin.locations.*','purchase-years.*','room-functions.*','inventory-conditions.*') && ! request()->routeIs('admin.users.*') ? 'menu-open' : '' }}">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-database"></i>
                <p>Master Data <i class="fas fa-angle-left right"></i></p>
            </a>
            <ul class="nav nav-treeview">
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
                @can('category.manage')
                <li class="nav-item">
                    <a href="{{ route('categories.index', ['tab' => 'klasifikasi']) }}"
                       class="nav-link {{ (request()->routeIs('categories.*') && request('tab') === 'klasifikasi') ? 'active' : '' }}">
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
                <li class="nav-item">
                    <a href="{{ route('categories.index', ['tab' => 'lokasi']) }}"
                       class="nav-link {{ request()->routeIs('categories.*') && request('tab') === 'lokasi' ? 'active' : '' }}">
                        <i class="nav-icon fas fa-trademark"></i>
                        <p>Merk</p>
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
                @can('supplier.manage')
                <li class="nav-item">
                    <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-truck"></i>
                        <p>Supplier</p>
                    </a>
                </li>
                @endcan
                @can('unit.manage')
                <li class="nav-item">
                    <a href="{{ route('categories.index', ['tab' => 'units']) }}"
                       class="nav-link {{ request()->routeIs('categories.*') && request('tab') === 'units' ? 'active' : '' }}">
                        <i class="nav-icon fas fa-ruler"></i>
                        <p>Satuan</p>
                    </a>
                </li>
                @endcan
                @can('category.manage')
                <li class="nav-item">
                    <a href="{{ route('categories.index', ['tab' => 'lokasi']) }}#section-kondisi"
                       class="nav-link">
                        <i class="nav-icon fas fa-heartbeat"></i>
                        <p>Kondisi</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('categories.index', ['tab' => 'lokasi']) }}"
                       class="nav-link">
                        <i class="nav-icon fas fa-toggle-on"></i>
                        <p>Status</p>
                    </a>
                </li>
                @endcan
                @can('unit.manage')
                <li class="nav-item">
                    <a href="{{ route('categories.index', ['tab' => 'units']) }}"
                       class="nav-link {{ request()->routeIs('org-units.*') || (request()->routeIs('categories.*') && request('tab') === 'units') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-sitemap"></i>
                        <p>Unit Kerja</p>
                    </a>
                </li>
                @endcan
            </ul>
        </li>
        @endif

        {{-- ========== OPERASIONAL ========== --}}
        @if($isViewer)
            @canany(['borrowing.view', 'borrowing.create'])
            <li class="nav-header">OPERASIONAL</li>
            <li class="nav-item {{ request()->routeIs('borrowings.*') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-handshake"></i>
                    <p>Peminjaman <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('borrowing.create')
                    <li class="nav-item">
                        <a href="{{ route('borrowings.create') }}" class="nav-link {{ request()->routeIs('borrowings.create') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-plus-circle"></i>
                            <p>Pinjam Inventaris</p>
                        </a>
                    </li>
                    @endcan
                    @can('borrowing.view')
                    <li class="nav-item">
                        <a href="{{ route('borrowings.index') }}" class="nav-link {{ request()->routeIs('borrowings.index','borrowings.show') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-history"></i>
                            <p>Riwayat Peminjaman Saya</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endcanany

            @can('borrowing.view')
            <li class="nav-header">LAPORAN</li>
            <li class="nav-item">
                <a href="{{ route('borrowings.index') }}" class="nav-link {{ request()->routeIs('borrowings.*') && ! request()->routeIs('borrowings.create') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-file-alt"></i>
                    <p>Laporan Peminjaman Saya</p>
                </a>
            </li>
            @endcan

            <li class="nav-header">AKUN</li>
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
            <li class="nav-header">OPERASIONAL</li>
            <li class="nav-item {{ request()->routeIs('maintenances.*','maintenance-schedules.*','teknisi-dashboard.*','maintenance-reports.*') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-tools"></i>
                    <p>Maintenance <i class="fas fa-angle-left right"></i></p>
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
            <li class="nav-header">LAPORAN</li>
            <li class="nav-item">
                <a href="{{ route('maintenance-reports.index') }}" class="nav-link {{ request()->routeIs('maintenance-reports.*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-file-alt"></i>
                    <p>Laporan Maintenance</p>
                </a>
            </li>
            @endcan

            <li class="nav-header">AKUN</li>
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
                || $user->can('mutation.view') || $user->can('deletion-approval.view') || $user->can('procurement.manage')
            )
            <li class="nav-header">OPERASIONAL</li>

            {{-- Peminjaman --}}
            @canany(['borrowing.view', 'borrowing.create', 'borrowing.approve'])
            <li class="nav-item {{ request()->routeIs('borrowings.*','borrowing-approvals.*') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-handshake"></i>
                    <p>Peminjaman <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('borrowing.create')
                    <li class="nav-item">
                        <a href="{{ route('borrowings.create') }}" class="nav-link {{ request()->routeIs('borrowings.create') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-plus-circle"></i>
                            <p>Pinjam Inventaris</p>
                        </a>
                    </li>
                    @endcan
                    @can('borrowing.approve')
                    <li class="nav-item">
                        <a href="{{ route('borrowings.index', ['status' => BorrowingStatus::MenungguPersetujuan->value]) }}"
                           class="nav-link {{ request()->routeIs('borrowings.*') && request('status') === BorrowingStatus::MenungguPersetujuan->value ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-check"></i>
                            <p>Approval Peminjaman</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('borrowing-approvals.index') }}" class="nav-link {{ request()->routeIs('borrowing-approvals.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-clipboard-check"></i>
                            <p>Riwayat Approval</p>
                        </a>
                    </li>
                    @endcan
                    @can('borrowing.view')
                    <li class="nav-item">
                        <a href="{{ route('borrowings.index') }}"
                           class="nav-link {{ request()->routeIs('borrowings.index','borrowings.show','borrowings.edit') && ! request()->filled('status') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-history"></i>
                            <p>Riwayat Peminjaman</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endcanany

            {{-- Maintenance --}}
            @can('maintenance.view')
            <li class="nav-item {{ request()->routeIs('maintenances.*','maintenance-schedules.*','maintenance-dashboard.*','maintenance-approvals.*','inventory.damaged.*') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-tools"></i>
                    <p>Maintenance <i class="fas fa-angle-left right"></i></p>
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

            {{-- Operasional Lainnya --}}
            @if($user->can('mutation.view') || $user->can('procurement.manage') || $user->can('stock-opname.view') || ($isSuperAdmin && $user->can('deletion-approval.view')))
            <li class="nav-item {{ request()->routeIs('mutations.*','stock-opnames.*','deletion-approvals.*') || (request()->routeIs('categories.*') && request('tab') === 'pengadaan') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-briefcase"></i>
                    <p>Operasional Lainnya <i class="fas fa-angle-left right"></i></p>
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
                        @if($isSuperAdmin)
                        <li class="nav-item">
                            <a href="{{ route('deletion-approvals.index') }}" class="nav-link {{ request()->routeIs('deletion-approvals.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-trash-alt"></i>
                                <p>Persetujuan Hapus</p>
                            </a>
                        </li>
                        @endif
                    @endcan
                </ul>
            </li>
            @endif
            @endif

            {{-- Laporan --}}
            @if($user->can('report.view') || $user->can('maintenance.view') || $user->can('stock-opname.view'))
            <li class="nav-header">LAPORAN</li>
            <li class="nav-item {{ request()->routeIs('reports.*','maintenance-reports.*','stock-opnames.*') && ! request()->routeIs('stock-opnames.create') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-chart-bar"></i>
                    <p>Laporan <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('report.view')
                    <li class="nav-item">
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.index') || (request()->routeIs('reports.show') && ! in_array(request()->route('type'), ['dipinjam','rusak','dimutasi','maintenance'], true)) ? 'active' : '' }}">
                            <i class="nav-icon fas fa-boxes"></i>
                            <p>Laporan Inventaris</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('reports.show', 'dipinjam') }}" class="nav-link {{ request()->routeIs('reports.*') && request()->route('type') === 'dipinjam' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-handshake"></i>
                            <p>Laporan Peminjaman</p>
                        </a>
                    </li>
                    @endcan
                    @can('maintenance.view')
                    <li class="nav-item">
                        <a href="{{ route('maintenance-reports.index') }}" class="nav-link {{ request()->routeIs('maintenance-reports.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tools"></i>
                            <p>Laporan Maintenance</p>
                        </a>
                    </li>
                    @endcan
                    @can('report.view')
                    <li class="nav-item">
                        <a href="{{ route('reports.show', 'rusak') }}" class="nav-link {{ request()->routeIs('reports.*') && request()->route('type') === 'rusak' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-exclamation-triangle"></i>
                            <p>Laporan Barang Rusak</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('reports.show', 'dimutasi') }}" class="nav-link {{ request()->routeIs('reports.*') && request()->route('type') === 'dimutasi' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-exchange-alt"></i>
                            <p>Laporan Mutasi</p>
                        </a>
                    </li>
                    @endcan
                    @can('stock-opname.view')
                    <li class="nav-item">
                        <a href="{{ route('stock-opnames.index') }}" class="nav-link {{ request()->routeIs('stock-opnames.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Laporan Stock Opname</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endif

            {{-- Sistem (Super Admin primarily; Admin may have none of these) --}}
            @canany(['user.manage', 'settings.manage', 'backup.manage', 'audit-log.view', 'trash.view'])
            <li class="nav-header">SISTEM</li>
            <li class="nav-item {{ request()->routeIs('admin.users.*','admin.settings.*','audit-logs.*','trash.*') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-user-shield"></i>
                    <p>Sistem <i class="fas fa-angle-left right"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    @can('user.manage')
                    <li class="nav-item">
                        <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-users"></i>
                            <p>User / Role / Permission</p>
                        </a>
                    </li>
                    @endcan
                    @can('trash.view')
                    <li class="nav-item">
                        <a href="{{ route('trash.index') }}" class="nav-link {{ request()->routeIs('trash.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-trash text-secondary"></i>
                            <p>Admin (Trash)</p>
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
                            <p>Setting</p>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endcanany
        @endif
    </ul>
</nav>
