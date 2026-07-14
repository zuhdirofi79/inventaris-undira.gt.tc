<?php

namespace App\Http\Controllers;

use App\Enums\InventoryStatus;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard.index', $this->getStats());
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->getStats());
    }

    public function charts(): JsonResponse
    {
        $byStatus = InventoryItem::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byCategory = InventoryItem::query()
            ->join('categories', 'inventory_items.category_id', '=', 'categories.id')
            ->selectRaw('categories.name as label, COUNT(*) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $monthlyAdditions = InventoryItem::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $byCampus = InventoryItem::query()
            ->join('rooms', 'inventory_items.room_id', '=', 'rooms.id')
            ->join('floors', 'rooms.floor_id', '=', 'floors.id')
            ->join('buildings', 'floors.building_id', '=', 'buildings.id')
            ->join('campuses', 'buildings.campus_id', '=', 'campuses.id')
            ->selectRaw('campuses.name as label, COUNT(*) as total')
            ->groupBy('campuses.name')
            ->orderByDesc('total')
            ->get();

        $byCondition = InventoryItem::query()
            ->selectRaw('`condition`, COUNT(*) as total')
            ->groupBy('condition')
            ->pluck('total', 'condition');

        return response()->json([
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'by_campus' => $byCampus,
            'by_condition' => $byCondition,
            'monthly_additions' => $monthlyAdditions,
        ]);
    }

    public function recentItems(): JsonResponse
    {
        $items = InventoryItem::query()
            ->with(['category', 'room.floor.building.campus'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json($items);
    }

    public function recentActivity(): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('user')
            ->latest()
            ->limit(15)
            ->get();

        return response()->json($logs);
    }

    protected function getStats(): array
    {
        return [
            'total_inventory' => InventoryItem::count(),
            'total_campuses' => Campus::where('is_active', true)->count(),
            'total_buildings' => Building::where('is_active', true)->count(),
            'total_rooms' => Room::where('is_active', true)->count(),
            'active_inventory' => InventoryItem::where('status', InventoryStatus::Aktif)->count(),
            'good_items' => InventoryItem::where('status', InventoryStatus::Aktif)->count(),
            'borrowed' => InventoryItem::where('status', InventoryStatus::Dipinjam)->count(),
            'maintenance' => InventoryItem::where('status', InventoryStatus::Maintenance)->count(),
            'damaged' => InventoryItem::whereIn('status', [InventoryStatus::RusakRingan, InventoryStatus::RusakBerat])->count(),
            'lost' => InventoryItem::where('status', InventoryStatus::Hilang)->count(),
            'warranty_expiring' => InventoryItem::warrantyExpiringSoon(30)->count(),
            'active_borrowings' => Borrowing::query()->where('status', 'dipinjam')->count(),
            'pending_maintenance' => Maintenance::query()->whereDate('next_schedule', '<=', now()->addDays(7))->count(),
        ];
    }
}
