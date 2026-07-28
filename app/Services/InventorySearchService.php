<?php

namespace App\Services;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\Room;
use App\Models\SubCategory;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Collection;

class InventorySearchService
{
    public function __construct(
        protected InventoryRepository $repository,
    ) {}

    public function normalizeCode(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('~/scan/([^/?]+)~i', $raw, $m)) {
            return rawurldecode($m[1]);
        }

        return $raw;
    }

    /**
     * @return list<array{id:int,text:string}>
     */
    public function campusOptions(): array
    {
        return $this->repository->activeCampuses()
            ->map(fn (Campus $c) => [
                'id' => $c->id,
                'text' => $c->code.' — '.$c->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,text:string}>
     */
    public function buildingOptions(int $campusId): array
    {
        if ($campusId < 1) {
            return [];
        }

        return $this->repository->buildingsForCampus($campusId)
            ->map(fn (Building $b) => [
                'id' => $b->id,
                'text' => $b->code.' — '.$b->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,text:string}>
     */
    public function floorOptions(int $buildingId): array
    {
        if ($buildingId < 1) {
            return [];
        }

        return $this->repository->floorsForBuilding($buildingId)
            ->map(fn (Floor $f) => [
                'id' => $f->id,
                'text' => $f->code.' — '.$f->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,text:string}>
     */
    public function roomOptions(int $floorId): array
    {
        if ($floorId < 1) {
            return [];
        }

        return $this->repository->roomsForFloor($floorId)
            ->map(fn (Room $r) => [
                'id' => $r->id,
                'text' => $r->code.' — '.$r->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,text:string}>
     */
    public function categoryOptions(int $roomId, ?string $status = null): array
    {
        if ($roomId < 1) {
            return [];
        }

        return $this->repository->categoriesForRoom($roomId, $status)
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'text' => $c->code.' — '.$c->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,text:string}>
     */
    public function subCategoryOptions(int $roomId, int $categoryId, ?string $status = null): array
    {
        if ($roomId < 1 || $categoryId < 1) {
            return [];
        }

        return $this->repository->subCategoriesForRoomCategory($roomId, $categoryId, $status)
            ->map(fn (SubCategory $s) => [
                'id' => $s->id,
                'text' => $s->code.' — '.$s->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Location-mode Select2 results.
     *
     * @param  array{status?:string|null,room_id?:int,category_id?:int,sub_category_id?:int}  $filters
     * @return list<array<string, mixed>>
     */
    public function locationItems(array $filters, string $q = '', int $limit = 40): array
    {
        $roomId = (int) ($filters['room_id'] ?? 0);
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $subCategoryId = (int) ($filters['sub_category_id'] ?? 0);

        if ($roomId < 1 || $categoryId < 1 || $subCategoryId < 1) {
            return [];
        }

        if ($q !== '' && mb_strlen($q) < 2) {
            return [];
        }

        return $this->mapSelect2Rows(
            $this->repository->listForLocation($filters, $q !== '' ? $q : null, $limit)
        );
    }

    /**
     * Quick search — minimum 2 characters.
     *
     * @param  array{status?:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function search(string $q, array $filters = [], int $limit = 30): array
    {
        $q = $this->normalizeCode($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return $this->mapSelect2Rows(
            $this->repository->search($q, $filters, $limit)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(?int $id): ?array
    {
        if (! $id) {
            return null;
        }

        $item = $this->repository->findDetail($id);

        return $item ? $this->detailPayload($item) : null;
    }

    /**
     * @return array{found:bool,item?:array<string,mixed>,message?:string}
     */
    public function resolveCode(string $code): array
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            return ['found' => false, 'message' => 'Kode kosong.'];
        }

        $item = $this->repository->findByCode($code);
        if (! $item) {
            return ['found' => false, 'message' => 'Inventaris tidak ditemukan.'];
        }

        return [
            'found' => true,
            'item' => $this->detailPayload($item),
        ];
    }

    /**
     * @param  Collection<int, InventoryItem>  $items
     * @return list<array<string, mixed>>
     */
    protected function mapSelect2Rows(Collection $items): array
    {
        return $items->map(function (InventoryItem $item) {
            $roomLabel = $item->room?->code ?: ($item->room?->name ?: '—');
            $status = $item->status instanceof InventoryStatus
                ? $item->status
                : InventoryStatus::tryFrom((string) $item->status);
            $statusLabel = $status?->label() ?? (string) $item->status;

            return [
                'id' => $item->id,
                'text' => $item->inventory_number.' — '.$item->name.' — '.$roomLabel.' — '.$statusLabel,
                'inventory_number' => $item->inventory_number,
                'name' => $item->name,
                'location' => $roomLabel,
                'status' => $status?->value ?? (string) $item->status,
                'status_label' => $statusLabel,
                'barcode' => $item->barcode,
                'serial_number' => $item->serial_number,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(InventoryItem $item): array
    {
        $status = $item->status instanceof InventoryStatus
            ? $item->status
            : InventoryStatus::tryFrom((string) $item->status);

        $condition = $item->condition instanceof InventoryCondition
            ? $item->condition
            : InventoryCondition::tryFrom((string) $item->condition);

        $activeBorrowing = null;
        $openBorrowing = Borrowing::query()
            ->where(function ($q) use ($item) {
                $q->where('inventory_item_id', $item->id)
                    ->orWhereHas('items', fn ($i) => $i->where('inventory_item_id', $item->id)
                        ->whereIn('status', BorrowingItemStatus::blockingValues()));
            })
            ->whereIn('status', BorrowingStatus::blockingValues())
            ->latest('borrow_date')
            ->first(['id', 'borrower_name', 'borrow_date', 'expected_return_date', 'status']);

        if ($openBorrowing) {
            $activeBorrowing = [
                'id' => $openBorrowing->id,
                'borrower_name' => $openBorrowing->borrower_name,
                'borrow_date' => optional($openBorrowing->borrow_date)->format('Y-m-d'),
                'expected_return_date' => optional($openBorrowing->expected_return_date)->format('Y-m-d'),
                'status' => $openBorrowing->status instanceof BorrowingStatus
                    ? $openBorrowing->status->value
                    : (string) $openBorrowing->status,
            ];
        }

        $campus = $item->room?->floor?->building?->campus;
        $building = $item->room?->floor?->building;
        $floor = $item->room?->floor;

        $borrowable = ($status?->isBorrowable() ?? false) && $openBorrowing === null;
        $availableQty = $borrowable
            ? app(BorrowingItemResolver::class)->availableCount($item)
            : 0;

        return [
            'id' => $item->id,
            'inventory_number' => $item->inventory_number,
            'name' => $item->name,
            'category' => $item->category
                ? $item->category->code.' — '.$item->category->name
                : '—',
            'sub_category' => $item->subCategory
                ? $item->subCategory->code.' — '.$item->subCategory->name
                : '—',
            'campus' => $campus ? $campus->code.' — '.$campus->name : '—',
            'building' => $building ? $building->code.' — '.$building->name : '—',
            'floor' => $floor ? $floor->code.' — '.$floor->name : '—',
            'room' => $item->room ? $item->room->code.' — '.$item->room->name : '—',
            'location' => $item->full_location ?: '—',
            'room_code' => $item->room?->code,
            'pic' => $item->picDisplayName() ?: '—',
            'condition' => $condition?->value,
            'condition_label' => $condition?->label() ?? '—',
            'status' => $status?->value ?? (string) $item->status,
            'status_label' => $status?->label() ?? (string) $item->status,
            'barcode' => $item->barcode ?: '—',
            'serial_number' => $item->serial_number ?: '—',
            'qr_url' => $item->qr_url,
            'borrowable' => $borrowable,
            'available_qty' => $availableQty,
            'active_borrowing' => $activeBorrowing,
            'campus_id' => $campus?->id,
            'building_id' => $building?->id,
            'floor_id' => $floor?->id,
            'room_id' => $item->room_id,
            'category_id' => $item->category_id,
            'sub_category_id' => $item->sub_category_id,
            'warranty_info' => $item->warranty_info ?: '—',
            'warranty_expiry' => optional($item->warranty_expiry)->format('Y-m-d'),
            'warranty_label' => $this->warrantyLabel($item),
            'purchase_year' => $item->purchase_year,
        ];
    }

    /**
     * Rich payload for Work Order / Schedule forms (history, schedule, latest WO).
     *
     * @return array<string, mixed>|null
     */
    public function maintenanceContext(?int $id): ?array
    {
        if (! $id) {
            return null;
        }

        $item = $this->repository->findForMaintenanceContext($id);

        return $item ? $this->maintenanceContextPayload($item) : null;
    }

    /**
     * Resolve QR/barcode/serial then return maintenance context (scan flows).
     *
     * @return array{found:bool,item?:array<string,mixed>,message?:string}
     */
    public function resolveCodeForMaintenance(string $code): array
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            return ['found' => false, 'message' => 'Kode kosong.'];
        }

        $item = $this->repository->findByCodeForMaintenanceContext($code);
        if (! $item) {
            return ['found' => false, 'message' => 'Inventaris tidak ditemukan.'];
        }

        return [
            'found' => true,
            'item' => $this->maintenanceContextPayload($item),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function maintenanceContextPayload(InventoryItem $item): array
    {
        $base = $this->detailPayload($item);
        $primary = $item->relationLoaded('photos') ? $item->photos->first() : null;

        $history = collect($item->relationLoaded('maintenances') ? $item->maintenances : [])
            ->map(function (Maintenance $m) {
                $type = $m->maintenance_type;
                $status = $m->status;

                return [
                    'id' => $m->id,
                    'maintenance_number' => $m->maintenance_number,
                    'service_date' => optional($m->service_date)->format('Y-m-d'),
                    'type' => $type?->value,
                    'type_label' => $type?->label() ?? '—',
                    'status' => $status instanceof MaintenanceStatus ? $status->value : (string) $status,
                    'status_label' => $status instanceof MaintenanceStatus ? $status->label() : (string) $status,
                    'technician' => $m->technician ?: '—',
                    'url' => route('maintenances.show', $m),
                ];
            })
            ->values()
            ->all();

        $schedules = collect($item->relationLoaded('maintenanceSchedules') ? $item->maintenanceSchedules : [])
            ->map(function (MaintenanceSchedule $s) {
                $type = $s->maintenance_type;
                $unit = $s->interval_unit;

                return [
                    'id' => $s->id,
                    'type_label' => $type?->label() ?? '—',
                    'interval_label' => trim(($s->interval_value ?? '').' '.($unit?->label() ?? '')),
                    'next_maintenance_date' => optional($s->next_maintenance_date)->format('Y-m-d'),
                    'responsible_pic' => $s->responsible_pic ?: '—',
                    'is_active' => (bool) $s->is_active,
                    'url' => route('maintenance-schedules.show', $s),
                ];
            })
            ->values()
            ->all();

        $latestWo = $history[0] ?? null;

        return array_merge($base, [
            'category_name' => $item->category?->name,
            'sub_category_name' => $item->subCategory?->name,
            'campus_name' => $item->room?->floor?->building?->campus?->name,
            'building_name' => $item->room?->floor?->building?->name,
            'floor_name' => $item->room?->floor?->name,
            'room_name' => $item->room?->name,
            'procurement_type' => $item->procurementType?->name ?: '—',
            'photo_url' => $primary?->url,
            'create_url' => route('maintenances.create', ['inventory_item_id' => $item->id]),
            'schedule_create_url' => route('maintenance-schedules.create', ['inventory_item_id' => $item->id]),
            'maintenance_history' => $history,
            'maintenance_schedules' => $schedules,
            'latest_work_order' => $latestWo,
        ]);
    }

    protected function warrantyLabel(InventoryItem $item): string
    {
        $info = trim((string) ($item->warranty_info ?? ''));
        $expiry = $item->warranty_expiry;
        if ($info === '' && ! $expiry) {
            return '—';
        }
        $parts = [];
        if ($info !== '') {
            $parts[] = $info;
        }
        if ($expiry) {
            $parts[] = 's/d '.$expiry->format('d/m/Y');
            if ($expiry->isPast()) {
                $parts[] = '(kadaluarsa)';
            }
        }

        return implode(' · ', $parts);
    }
}
