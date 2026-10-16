<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Services\DashboardBorrowingScheduleService;
use App\Services\DashboardRecentBorrowingsService;
use App\Services\DashboardStatsService;
use App\Services\DashboardTreeService;
use App\Services\DashboardCampusExecutiveService;
use App\Services\OnlinePresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardTreeService $treeService,
        protected DashboardStatsService $statsService,
        protected DashboardCampusExecutiveService $campusExecutive,
        protected DashboardBorrowingScheduleService $borrowingSchedule,
        protected DashboardRecentBorrowingsService $recentBorrowings,
        protected OnlinePresenceService $onlinePresence,
    ) {}

    public function index(): View|\Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        if ($user?->hasRole('teknisi') && ! $user->hasAnyRole(['super-admin', 'admin', 'staff'])) {
            return redirect()->route('teknisi-dashboard.index');
        }

        $stats = $this->statsService->summary();

        $recentItems = InventoryItem::query()
            ->with(['category', 'room.floor.building.campus'])
            ->latest()
            ->limit(10)
            ->get();

        $recentActivity = collect();
        if (auth()->user()?->can('audit-log.view')) {
            $recentActivity = AuditLog::query()
                ->with('user')
                ->latest()
                ->limit(15)
                ->get()
                ->map(function (AuditLog $log) {
                    $action = $log->action instanceof AuditAction
                        ? $log->action
                        : AuditAction::tryFrom((string) $log->action);

                    $role = is_array($log->new_values) ? ($log->new_values['role'] ?? null) : null;

                    return [
                        'user' => $log->user?->name ?? 'Sistem',
                        'role' => $role,
                        'action' => $action?->label() ?? (string) $log->action,
                        'description' => $this->friendlyAuditDescription($log->description),
                        'ip' => $log->ip_address,
                        'created_at' => $log->created_at?->toIso8601String(),
                        'created_at_human' => $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    ];
                });
        }

        $scheduleToday = [];
        $scheduleUpcoming = [];
        $onlineUsers = ['total' => 0, 'users' => []];
        $recentBorrowingPayload = [
            'submissions' => [],
            'approved' => [],
            'submissions_url' => null,
            'approved_url' => null,
        ];
        try {
            $scheduleToday = $this->borrowingSchedule->today();
            $scheduleUpcoming = $this->borrowingSchedule->upcoming();
        } catch (\Throwable) {
            report(new \RuntimeException('Dashboard borrowing schedule widgets failed to load.'));
        }
        try {
            $recentBorrowingPayload = $this->recentBorrowings->forUser($user);
        } catch (\Throwable) {
            report(new \RuntimeException('Dashboard recent borrowing widgets failed to load.'));
        }
        try {
            $onlineUsers = $this->onlinePresence->dashboardSnapshot($user);
        } catch (\Throwable) {
            report(new \RuntimeException('Dashboard online presence widget failed to load.'));
        }

        return view('dashboard.index', [
            ...$stats,
            'recent_items' => $recentItems,
            'recent_activity' => $recentActivity,
            'quick_actions' => $this->quickActions(),
            'executiveSummary' => $this->campusExecutive->summary(),
            'canRefreshCampusSummary' => (bool) $user?->hasAnyRole(['super-admin', 'admin']),
            'schedule_today' => $scheduleToday,
            'schedule_upcoming' => $scheduleUpcoming,
            'online_users' => $onlineUsers,
            'schedule_records_url' => ($user?->can('borrowing.view') || $user?->can('room-borrowing.view') || $user?->can('vehicle-borrowing.view'))
                ? route('borrowing-records.index')
                : null,
            'recent_borrowing_submissions' => $recentBorrowingPayload['submissions'],
            'recent_borrowing_approved' => $recentBorrowingPayload['approved'],
            'recent_borrowing_submissions_url' => $recentBorrowingPayload['submissions_url'],
            'recent_borrowing_approved_url' => $recentBorrowingPayload['approved_url'],
        ]);
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, permission: string}>
     */
    protected function quickActions(): array
    {
        $actions = [
            [
                'label' => 'Jelajahi Lokasi',
                'description' => 'Navigasi hierarki aset',
                'icon' => 'fa-map-marked-alt',
                'url' => route('campuses.index'),
                'permission' => 'location.view',
            ],
            [
                'label' => 'Preventive',
                'description' => 'Jadwal perawatan berkala',
                'icon' => 'fa-calendar-check',
                'url' => route('maintenance-schedules.index'),
                'permission' => 'maintenance.view',
            ],
            [
                'label' => 'Lihat Inventaris',
                'description' => 'Daftar aset aktif',
                'icon' => 'fa-boxes',
                'url' => route('inventory.index'),
                'permission' => 'inventory.view',
            ],
            [
                'label' => 'Tambah Inventaris',
                'description' => 'Lewat konfigurasi master',
                'icon' => 'fa-plus-circle',
                'url' => route('categories.index', ['tab' => 'konfigurasi', 'create' => 1]),
                'permission' => 'inventory.create',
            ],
            [
                'label' => 'Pinjam Inventaris',
                'description' => 'Ajukan peminjaman satu atau beberapa item inventaris.',
                'icon' => 'fa-hand-holding',
                'url' => route('borrowings.create'),
                'permission' => 'borrowing.create',
            ],
            [
                'label' => 'Pinjam Ruangan',
                'description' => 'Ajukan penggunaan ruang kelas atau ruangan kampus.',
                'icon' => 'fa-door-open',
                'url' => route('room-borrowings.create'),
                'permission' => 'room-borrowing.create',
            ],
            [
                'label' => 'Pinjam Kendaraan',
                'description' => 'Ajukan kendaraan operasional untuk keperluan perjalanan.',
                'icon' => 'fa-car-side',
                'url' => route('vehicle-borrowings.create'),
                'permission' => 'vehicle-borrowing.create',
            ],
            [
                'label' => 'Mutasi',
                'description' => 'Pindahkan lokasi barang',
                'icon' => 'fa-exchange-alt',
                'url' => route('mutations.create'),
                'permission' => 'mutation.create',
            ],
            [
                'label' => 'Maintenance',
                'description' => 'Jadwalkan perawatan',
                'icon' => 'fa-wrench',
                'url' => route('maintenances.create'),
                'permission' => 'maintenance.create',
            ],
            [
                'label' => 'Stock Opname',
                'description' => 'Mulai pengecekan stok',
                'icon' => 'fa-clipboard-check',
                'url' => route('stock-opnames.create'),
                'permission' => 'stock-opname.create',
            ],
            [
                'label' => 'Scanner',
                'description' => 'Scan QR Code inventaris',
                'icon' => 'fa-qrcode',
                'url' => route('scanner.index'),
                'permission' => 'inventory.view',
            ],
            [
                'label' => 'Laporan',
                'description' => 'Cetak / export laporan',
                'icon' => 'fa-file-alt',
                'url' => route('reports.index'),
                'permission' => 'report.view',
            ],
        ];

        return array_values(array_filter(
            $actions,
            fn (array $action) => auth()->user()?->can($action['permission']) ?? false,
        ));
    }

    protected function friendlyAuditDescription(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }

        return match ($description) {
            'BORROWING_CREATED' => 'Peminjaman diajukan',
            'BORROWING_APPROVED' => 'Peminjaman disetujui',
            'BORROWING_REJECTED' => 'Peminjaman ditolak',
            'BORROWING_RETURNED' => 'Barang dikembalikan',
            'BORROWING_UPDATED' => 'Peminjaman diperbarui',
            'BORROWING_DELETED' => 'Peminjaman dihapus',
            'MAINTENANCE_CREATED' => 'Maintenance dicatat',
            'MAINTENANCE_UPDATED' => 'Maintenance diperbarui',
            'MAINTENANCE_DELETED' => 'Maintenance dihapus',
            'MUTATION_CREATED' => 'Mutasi inventaris dicatat',
            'STOCK_OPNAME_CREATED' => 'Stock opname dibuat',
            'STOCK_OPNAME_STARTED' => 'Stock opname dimulai',
            'STOCK_OPNAME_COMPLETED' => 'Stock opname selesai',
            'STOCK_OPNAME_DELETED' => 'Stock opname dihapus',
            'CATEGORY_CREATED' => 'Kategori ditambahkan',
            'CATEGORY_UPDATED' => 'Kategori diperbarui',
            'SUPPLIER_CREATED' => 'Supplier ditambahkan',
            'VENDOR_CREATED' => 'Vendor ditambahkan',
            'CAMPUS_CREATED' => 'Kampus ditambahkan',
            'DELETION_APPROVAL_REQUESTED' => 'Permintaan hapus diajukan',
            'DELETION_APPROVAL_APPROVED' => 'Penghapusan disetujui',
            'DELETION_APPROVAL_REJECTED' => 'Penghapusan ditolak',
            'INVENTORY_BULK_RELOCATED' => 'Inventaris dipindahkan massal',
            'USER_CREATED' => 'Pengguna ditambahkan',
            'USER_UPDATED' => 'Pengguna diperbarui',
            'USER_DELETED' => 'Pengguna dihapus',
            default => $description,
        };
    }

    public function tree(): JsonResponse
    {
        return response()->json(['nodes' => $this->treeService->campuses()]);
    }

    public function treeBuildings(Campus $campus): JsonResponse
    {
        return response()->json(['nodes' => $this->treeService->buildings($campus)]);
    }

    public function treeFloors(Building $building): JsonResponse
    {
        return response()->json(['nodes' => $this->treeService->floors($building)]);
    }

    public function treeRooms(Floor $floor): JsonResponse
    {
        return response()->json(['nodes' => $this->treeService->rooms($floor)]);
    }

    public function treeCategories(Room $room): JsonResponse
    {
        return response()->json(['nodes' => $this->treeService->categories($room)]);
    }

    public function treeItems(Room $room, Category $category): JsonResponse
    {
        return response()->json(['nodes' => $this->treeService->items($room, $category)]);
    }
}
