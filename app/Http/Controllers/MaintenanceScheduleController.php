<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceType;
use App\Http\Requests\StoreMaintenanceScheduleRequest;
use App\Http\Requests\UpdateMaintenanceScheduleRequest;
use App\Models\Campus;
use App\Models\MaintenanceSchedule;
use App\Services\InventorySearchService;
use App\Services\MaintenanceScheduleService;
use App\Support\InventarisCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use RuntimeException;

class MaintenanceScheduleController extends Controller
{
    public function __construct(
        protected MaintenanceScheduleService $schedules,
        protected InventorySearchService $inventorySearch,
    ) {}

    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:all,active,inactive,overdue'],
            'filter' => ['nullable', 'string', 'in:all,due_today,due_week,overdue,inactive'],
        ]);

        $filter = $request->string('filter')->toString() ?: 'all';
        $status = $request->string('status')->toString();
        $campusId = $request->filled('campus_id') ? $request->integer('campus_id') : null;
        $type = $request->filled('type') ? $request->string('type')->toString() : null;
        $q = $request->filled('q') ? $request->string('q')->toString() : null;

        $shared = [
            'q' => $q,
            'campus_id' => $campusId,
            'type' => $type,
        ];
        $listFilters = [
            ...$shared,
            'status' => $status !== '' ? $status : null,
            'filter' => $filter,
        ];

        $query = MaintenanceSchedule::query()
            ->with([
                'inventoryItem:id,inventory_number,name,room_id',
                'inventoryItem.room:id,name,code,floor_id',
                'inventoryItem.room.floor:id,name,code,floor_number,building_id',
                'inventoryItem.room.floor.building:id,name,code,campus_id',
                'inventoryItem.room.floor.building.campus:id,name,code',
            ]);

        $this->schedules->applySharedFilters($query, $shared);
        $this->schedules->applyStatusAndCardFilter($query, $listFilters);

        $items = $query->orderBy('next_maintenance_date')->paginate(20)->withQueryString();

        $filterQuery = array_filter([
            'q' => $q,
            'campus_id' => $campusId,
            'type' => $type,
            'status' => $status !== '' ? $status : null,
        ], fn ($v) => $v !== null && $v !== '');

        return view('operations.maintenance-schedules.index', [
            'schedules' => $items,
            'summary' => $this->schedules->summaryCounts($shared),
            'campusCounts' => $this->schedules->countsByCampus($listFilters),
            'filter' => $filter,
            'status' => $status,
            'types' => MaintenanceType::cases(),
            'campuses' => Cache::remember(
                InventarisCache::key('campuses.active.dropdown'),
                InventarisCache::TTL,
                fn () => Campus::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
            ),
            'filterQuery' => $filterQuery,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedId = $request->integer('inventory_item_id') ?: (int) old('inventory_item_id');
        $type = (string) old('maintenance_type', MaintenanceType::Preventive->value);
        $preview = $selectedId > 0
            ? $this->schedules->previewForInventory($selectedId, $type)
            : null;

        return view('operations.maintenance-schedules.create', [
            'preview' => $preview,
            'maintenanceContext' => $preview['inventory'] ?? null,
            'types' => MaintenanceType::cases(),
            'units' => MaintenanceIntervalUnit::cases(),
            'previewUrl' => route('maintenance-schedules.preview'),
            'suggestNextUrl' => route('maintenance-schedules.suggest-next'),
        ]);
    }

    /**
     * AJAX: inventory selected → last maintenance, interval/reminder/PIC suggestions, timeline, duplicate flag.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'min:1'],
            'maintenance_type' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->schedules->previewForInventory(
                (int) $data['inventory_item_id'],
                $data['maintenance_type'] ?? MaintenanceType::Preventive->value
            )
        );
    }

    /**
     * AJAX: recompute next maintenance date from interval.
     */
    public function suggestNext(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'min:1'],
            'interval_value' => ['required', 'integer', 'min:1', 'max:3650'],
            'interval_unit' => ['required', 'string'],
            'remind_before_days' => ['nullable', 'integer', 'in:7,14,30'],
        ]);

        $next = $this->schedules->suggestNextDate(
            (int) $data['inventory_item_id'],
            (int) $data['interval_value'],
            $data['interval_unit']
        );
        $remind = (int) ($data['remind_before_days'] ?? 7);

        return response()->json([
            'next_maintenance_date' => $next->toDateString(),
            'reminder_starts_at' => $next->copy()->subDays($remind)->toDateString(),
        ]);
    }

    public function store(StoreMaintenanceScheduleRequest $request): RedirectResponse
    {
        try {
            $schedule = $this->schedules->create($request->validated(), $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['maintenance_type' => $e->getMessage()]);
        }

        return redirect()
            ->route('maintenance-schedules.show', $schedule)
            ->with('success', 'Jadwal pemeliharaan preventif berhasil dibuat. Tanggal berikutnya: '.$schedule->next_maintenance_date?->format('d/m/Y').'.');
    }

    public function show(MaintenanceSchedule $maintenance_schedule): View
    {
        $maintenance_schedule->load([
            'inventoryItem:id,inventory_number,name,room_id',
            'inventoryItem.room:id,name,code,floor_id',
            'inventoryItem.room.floor:id,name,code,floor_number,building_id',
            'inventoryItem.room.floor.building:id,name,code,campus_id',
            'inventoryItem.room.floor.building.campus:id,name,code',
            'creator:id,name',
        ]);

        return view('operations.maintenance-schedules.show', [
            'schedule' => $maintenance_schedule,
            'timeline' => $this->schedules->buildTimeline(
                (int) $maintenance_schedule->inventory_item_id,
                $maintenance_schedule
            ),
            'lastMaintenance' => $this->schedules->previewForInventory(
                (int) $maintenance_schedule->inventory_item_id,
                $maintenance_schedule->maintenance_type?->value
            )['last_maintenance'] ?? null,
        ]);
    }

    public function edit(MaintenanceSchedule $maintenance_schedule): View
    {
        $maintenance_schedule->load('inventoryItem');

        return view('operations.maintenance-schedules.edit', [
            'schedule' => $maintenance_schedule,
            'types' => MaintenanceType::cases(),
            'units' => MaintenanceIntervalUnit::cases(),
            'suggestNextUrl' => route('maintenance-schedules.suggest-next'),
        ]);
    }

    public function update(UpdateMaintenanceScheduleRequest $request, MaintenanceSchedule $maintenance_schedule): RedirectResponse
    {
        try {
            $this->schedules->update($maintenance_schedule, $request->validated(), $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['maintenance_type' => $e->getMessage()]);
        }

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
