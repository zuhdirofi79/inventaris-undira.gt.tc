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
use App\Services\DashboardStatsService;
use App\Services\DashboardTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardTreeService $treeService,
        protected DashboardStatsService $statsService,
    ) {}

    public function index(): View
    {
        $stats = $this->statsService->summary();

        $recentItems = InventoryItem::query()
            ->with(['category', 'room.floor.building.campus'])
            ->latest()
            ->limit(10)
            ->get();

        $recentActivity = AuditLog::query()
            ->with('user')
            ->latest()
            ->limit(15)
            ->get()
            ->map(function (AuditLog $log) {
                $action = $log->action instanceof AuditAction
                    ? $log->action
                    : AuditAction::tryFrom((string) $log->action);

                return [
                    'user' => $log->user?->name ?? 'System',
                    'action' => $action?->label() ?? (string) $log->action,
                    'description' => $log->description,
                    'created_at' => optional($log->created_at)?->toIso8601String(),
                    'created_at_human' => optional($log->created_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                ];
            });

        return view('dashboard.index', [
            ...$stats,
            'recent_items' => $recentItems,
            'recent_activity' => $recentActivity,
        ]);
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

    public function stats(): JsonResponse
    {
        return response()->json($this->statsService->summary());
    }

    public function charts(): JsonResponse
    {
        return response()->json($this->statsService->chartData());
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
            ->get()
            ->map(function (AuditLog $log) {
                $action = $log->action instanceof AuditAction
                    ? $log->action
                    : AuditAction::tryFrom((string) $log->action);

                return [
                    'user' => ['name' => $log->user?->name ?? 'System'],
                    'action' => ['label' => $action?->label() ?? (string) $log->action],
                    'description' => $log->description,
                    'created_at' => optional($log->created_at)?->toIso8601String(),
                ];
            });

        return response()->json($logs);
    }
}
