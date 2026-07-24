<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use App\Services\TrashCascadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar barang rusak / tidak dapat diperbaiki / perlu penggantian.
 * Soft-delete hanya diizinkan untuk Primary Super Admin.
 */
class DamagedInventoryController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
        protected AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('inventory.view') ?? false, 403);

        $statuses = [
            InventoryStatus::RusakRingan->value,
            InventoryStatus::RusakBerat->value,
            InventoryStatus::TidakDapatDiperbaiki->value,
            InventoryStatus::PerluPenggantian->value,
        ];

        $items = InventoryItem::query()
            ->with(['category', 'subCategory', 'room.floor.building.campus'])
            ->whereIn('status', $statuses)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('inventory_number', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('responsible_person', 'like', $term)
                        ->orWhereHas('pic', fn ($pic) => $pic->where('name', 'like', $term));
                });
            })
            ->with(['room.floor.building.campus', 'pic'])
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.damaged.index', [
            'items' => $items,
            'filterStatuses' => [
                InventoryStatus::RusakRingan,
                InventoryStatus::RusakBerat,
                InventoryStatus::TidakDapatDiperbaiki,
                InventoryStatus::PerluPenggantian,
            ],
            'canDelete' => $request->user()?->isPrimarySuperAdmin() ?? false,
        ]);
    }

    public function destroy(Request $request, InventoryItem $inventory): RedirectResponse
    {
        abort_unless($request->user()?->isPrimarySuperAdmin() ?? false, 403);

        $status = $inventory->status instanceof InventoryStatus
            ? $inventory->status
            : InventoryStatus::tryFrom((string) $inventory->status);

        abort_unless($status?->isDamagedList() ?? false, 404);

        $this->trash->softDelete($inventory, $request->user());

        $this->audit->log(
            $request->user(),
            AuditAction::Delete,
            $inventory,
            ['status' => $status?->value, 'inventory_number' => $inventory->inventory_number],
            null,
            'DAMAGED_INVENTORY_SOFT_DELETED',
        );

        return back()->with('success', 'Barang rusak dipindahkan ke sampah. Hanya Primary Super Admin yang dapat menghapus dari daftar ini.');
    }
}
