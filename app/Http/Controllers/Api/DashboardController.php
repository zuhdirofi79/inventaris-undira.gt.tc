<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_inventory' => InventoryItem::count(),
            'active_inventory' => InventoryItem::where('status', 'aktif')->count(),
            'borrowed' => InventoryItem::where('status', 'dipinjam')->count(),
            'maintenance' => InventoryItem::where('status', 'maintenance')->count(),
            'damaged' => InventoryItem::whereIn('status', ['rusak_ringan', 'rusak_berat'])->count(),
            'lost' => InventoryItem::where('status', 'hilang')->count(),
            'warranty_expiring' => InventoryItem::warrantyExpiringSoon(30)->count(),
        ]);
    }

    public function recentItems(): JsonResponse
    {
        $items = InventoryItem::query()
            ->with(['category', 'room'])
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
            ->limit(10)
            ->get();

        return response()->json($logs);
    }
}
