<?php

namespace App\Http\Controllers;

use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Maintenance;
use App\Services\MaintenanceDashboardStatsService;
use Illuminate\View\View;

class TeknisiDashboardController extends Controller
{
    public function __construct(
        protected MaintenanceDashboardStatsService $stats,
    ) {}

    public function index(): View
    {
        $userId = auth()->id();
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $base = Maintenance::query()
            ->when($userId, fn ($q) => $q->where(function ($inner) use ($userId) {
                $inner->where('technician_user_id', $userId)
                    ->orWhere('created_by', $userId);
            }));

        $widgets = [
            'wo_today' => (clone $base)->whereDate('service_date', $today)->count(),
            'in_progress' => (clone $base)->whereIn('status', [
                MaintenanceStatus::InProgress->value,
                MaintenanceStatus::Revision->value,
            ])->count(),
            'pending_approval' => (clone $base)->where('status', MaintenanceStatus::PendingApproval)->count(),
            'completed_month' => (clone $base)->where('status', MaintenanceStatus::Completed)
                ->whereDate('approved_at', '>=', $monthStart)
                ->count(),
            'preventive_month' => \App\Models\MaintenanceSchedule::query()
                ->where('is_active', true)
                ->whereBetween('next_maintenance_date', [$monthStart, now()->endOfMonth()->toDateString()])
                ->count(),
            'damaged' => \App\Models\InventoryItem::query()
                ->whereIn('status', InventoryStatus::damagedValues())
                ->count(),
        ];

        $monthly = collect(range(5, 0))->map(function (int $ago) use ($userId) {
            $start = now()->subMonths($ago)->startOfMonth();
            $end = now()->subMonths($ago)->endOfMonth();
            $total = Maintenance::query()
                ->when($userId, fn ($q) => $q->where(function ($inner) use ($userId) {
                    $inner->where('technician_user_id', $userId)->orWhere('created_by', $userId);
                }))
                ->whereBetween('service_date', [$start->toDateString(), $end->toDateString()])
                ->count();

            return (object) [
                'ym' => $start->format('Y-m'),
                'label' => $start->translatedFormat('M Y'),
                'total' => $total,
            ];
        });

        $recent = Maintenance::query()
            ->with(['inventoryItem'])
            ->when($userId, fn ($q) => $q->where(function ($inner) use ($userId) {
                $inner->where('technician_user_id', $userId)->orWhere('created_by', $userId);
            }))
            ->latest()
            ->limit(10)
            ->get();

        return view('operations.teknisi-dashboard.index', [
            'widgets' => $widgets,
            'monthly' => $monthly,
            'recent' => $recent,
        ]);
    }
}
