<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceType;
use App\Http\Requests\StoreMaintenanceScheduleRequest;
use App\Http\Requests\UpdateMaintenanceScheduleRequest;
use App\Models\InventoryItem;
use App\Models\MaintenanceSchedule;
use App\Services\MaintenanceScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceScheduleController extends Controller
{
    public function __construct(
        protected MaintenanceScheduleService $schedules,
    ) {}

    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString() ?: 'all';

        $query = MaintenanceSchedule::query()
            ->with(['inventoryItem.room.floor.building.campus'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('responsible_pic', 'like', $term)
                        ->orWhereHas('inventoryItem', function ($item) use ($term) {
                            $item->where('inventory_number', 'like', $term)
                                ->orWhere('name', 'like', $term);
                        });
                });
            });

        $query = match ($filter) {
            'due_today' => $query->dueToday(),
            'due_week' => $query->dueThisWeek(),
            'overdue' => $query->overdue(),
            'inactive' => $query->where('is_active', false),
            default => $query->active(),
        };

        $items = $query->orderBy('next_maintenance_date')->paginate(20)->withQueryString();

        return view('operations.maintenance-schedules.index', [
            'schedules' => $items,
            'summary' => $this->schedules->summaryCounts(),
            'filter' => $filter,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedItem = null;
        $selectedId = $request->integer('inventory_item_id') ?: (int) old('inventory_item_id');
        if ($selectedId > 0) {
            $selectedItem = InventoryItem::query()->find($selectedId, ['id', 'inventory_number', 'name', 'responsible_person']);
        }

        return view('operations.maintenance-schedules.create', [
            'selectedItem' => $selectedItem,
            'types' => MaintenanceType::cases(),
            'units' => MaintenanceIntervalUnit::cases(),
        ]);
    }

    public function store(StoreMaintenanceScheduleRequest $request): RedirectResponse
    {
        $schedule = $this->schedules->create($request->validated(), $request->user());

        return redirect()
            ->route('maintenance-schedules.show', $schedule)
            ->with('success', 'Jadwal pemeliharaan preventif berhasil dibuat.');
    }

    public function show(MaintenanceSchedule $maintenance_schedule): View
    {
        $maintenance_schedule->load(['inventoryItem.room.floor.building.campus', 'creator']);

        return view('operations.maintenance-schedules.show', [
            'schedule' => $maintenance_schedule,
        ]);
    }

    public function edit(MaintenanceSchedule $maintenance_schedule): View
    {
        $maintenance_schedule->load('inventoryItem');

        return view('operations.maintenance-schedules.edit', [
            'schedule' => $maintenance_schedule,
            'types' => MaintenanceType::cases(),
            'units' => MaintenanceIntervalUnit::cases(),
        ]);
    }

    public function update(UpdateMaintenanceScheduleRequest $request, MaintenanceSchedule $maintenance_schedule): RedirectResponse
    {
        $this->schedules->update($maintenance_schedule, $request->validated(), $request->user());

        return redirect()
            ->route('maintenance-schedules.show', $maintenance_schedule)
            ->with('success', 'Jadwal pemeliharaan diperbarui.');
    }

    public function destroy(MaintenanceSchedule $maintenance_schedule): RedirectResponse
    {
        $this->schedules->delete($maintenance_schedule, request()->user());

        return redirect()
            ->route('maintenance-schedules.index')
            ->with('success', 'Jadwal pemeliharaan dihapus.');
    }

    public function advance(Request $request, MaintenanceSchedule $maintenance_schedule): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $maintenance_schedule) ?? false, 403);

        $this->schedules->completeCycle($maintenance_schedule, $request->user());

        return back()->with('success', 'Siklus ditandai selesai. Tanggal berikutnya telah digeser sesuai interval.');
    }
}
