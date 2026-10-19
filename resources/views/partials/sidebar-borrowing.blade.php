{{-- Nested borrowing menu. Approval links stay behind permission checks. --}}
@php
    use App\Enums\BorrowingStatus;

    $borrowingOpen = request()->routeIs(
        'borrowings.*',
        'borrowing-approvals.*',
        'room-borrowings.*',
        'room-activity-types.*',
        'room-budget-types.*',
        'vehicle-borrowings.*',
        'vehicle-fleet.*',
        'borrowing-records.*'
    );
    $inventoryOpen = request()->routeIs('borrowings.*', 'borrowing-approvals.*');
    $roomOpen = request()->routeIs('room-borrowings.*', 'room-activity-types.*', 'room-budget-types.*');
    $vehicleOpen = request()->routeIs('vehicle-borrowings.*', 'vehicle-fleet.*');
@endphp
<li class="nav-item {{ $borrowingOpen ? 'menu-open' : '' }}">
    <a href="#" class="nav-link">
        <i class="nav-icon fas fa-handshake"></i>
        <p>Peminjaman</p>
        <i class="fas fa-angle-left right" aria-hidden="true"></i>
    </a>
    <ul class="nav nav-treeview">
        @canany(['borrowing.view', 'borrowing.create', 'borrowing.approve'])
        <li class="nav-item {{ $inventoryOpen ? 'menu-open' : '' }}">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-box"></i>
                <p>Peminjaman Inventaris</p>
                <i class="fas fa-angle-left right" aria-hidden="true"></i>
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
                       class="nav-link {{ request()->routeIs('borrowings.index') && request('status') === BorrowingStatus::MenungguPersetujuan->value ? 'active' : '' }}">
                        <i class="nav-icon fas fa-user-check"></i>
                        <p>Approval Inventaris</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('borrowing-approvals.index') }}" class="nav-link {{ request()->routeIs('borrowing-approvals.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>Riwayat Approval Inventaris</p>
                    </a>
                </li>
                @endcan
            </ul>
        </li>
        @endcanany

        @canany(['room-borrowing.view', 'room-borrowing.create', 'room-borrowing.approve', 'room-activity-types.manage', 'room-budget-types.manage'])
        <li class="nav-item {{ $roomOpen ? 'menu-open' : '' }}">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-door-open"></i>
                <p>Peminjaman Ruangan</p>
                <i class="fas fa-angle-left right" aria-hidden="true"></i>
            </a>
            <ul class="nav nav-treeview">
                @can('room-borrowing.create')
                <li class="nav-item">
                    <a href="{{ route('room-borrowings.create') }}" class="nav-link {{ request()->routeIs('room-borrowings.create') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-plus-circle"></i>
                        <p>Pinjam Ruangan</p>
                    </a>
                </li>
                @endcan
                @can('room-borrowing.approve')
                <li class="nav-item">
                    <a href="{{ route('room-borrowings.approvals') }}" class="nav-link {{ request()->routeIs('room-borrowings.approvals') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-user-check"></i>
                        <p>Approval Ruangan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('room-borrowings.history') }}" class="nav-link {{ request()->routeIs('room-borrowings.history') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>Riwayat Approval Ruangan</p>
                    </a>
                </li>
                @endcan
                @can('room-activity-types.manage')
                <li class="nav-item">
                    <a href="{{ route('room-activity-types.index') }}" class="nav-link {{ request()->routeIs('room-activity-types.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Master Jenis Kegiatan</p>
                    </a>
                </li>
                @endcan
                @can('room-budget-types.manage')
                <li class="nav-item">
                    <a href="{{ route('room-budget-types.index') }}" class="nav-link {{ request()->routeIs('room-budget-types.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-wallet"></i>
                        <p>Master Anggaran</p>
                    </a>
                </li>
                @endcan
            </ul>
        </li>
        @endcanany

        @canany(['vehicle-borrowing.view', 'vehicle-borrowing.create', 'vehicle-borrowing.approve', 'vehicle-fleet.manage'])
        <li class="nav-item {{ $vehicleOpen ? 'menu-open' : '' }}">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-car"></i>
                <p>Peminjaman Kendaraan Operasional</p>
                <i class="fas fa-angle-left right" aria-hidden="true"></i>
            </a>
            <ul class="nav nav-treeview">
                @can('vehicle-borrowing.create')
                <li class="nav-item">
                    <a href="{{ route('vehicle-borrowings.create') }}" class="nav-link {{ request()->routeIs('vehicle-borrowings.create') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-plus-circle"></i>
                        <p>Pinjam Kendaraan</p>
                    </a>
                </li>
                @endcan
                @can('vehicle-borrowing.approve')
                <li class="nav-item">
                    <a href="{{ route('vehicle-borrowings.approvals') }}" class="nav-link {{ request()->routeIs('vehicle-borrowings.approvals') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-user-check"></i>
                        <p>Approval Kendaraan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('vehicle-borrowings.history') }}" class="nav-link {{ request()->routeIs('vehicle-borrowings.history') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>Riwayat Approval Kendaraan</p>
                    </a>
                </li>
                @endcan
                @can('vehicle-fleet.manage')
                <li class="nav-item">
                    <a href="{{ route('vehicle-fleet.index') }}" class="nav-link {{ request()->routeIs('vehicle-fleet.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Master Kendaraan</p>
                    </a>
                </li>
                @endcan
            </ul>
        </li>
        @endcanany

        @canany(['borrowing.view', 'room-borrowing.view', 'vehicle-borrowing.view'])
        <li class="nav-item">
            <a href="{{ route('borrowing-records.index') }}" class="nav-link {{ request()->routeIs('borrowing-records.*') ? 'active' : '' }}">
                <i class="nav-icon fas fa-history"></i>
                <p>Riwayat Peminjaman</p>
            </a>
        </li>
        @endcanany
    </ul>
</li>
