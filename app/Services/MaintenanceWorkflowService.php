<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\InventoryStatus;
use App\Enums\MaintenanceResult;
use App\Enums\MaintenanceStatus;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\MaintenanceStatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MaintenanceWorkflowService
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $beforePhotos
     */
    public function create(array $data, User $actor, array $beforePhotos = []): Maintenance
    {
        return DB::transaction(function () use ($data, $actor, $beforePhotos) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($data['inventory_item_id']);

            if ($item->status === InventoryStatus::Dipinjam) {
                throw new RuntimeException('Barang sedang dipinjam dan tidak dapat dimasukkan ke pemeliharaan.');
            }

            $openExists = Maintenance::query()
                ->where('inventory_item_id', $item->id)
                ->whereIn('status', [MaintenanceStatus::Waiting->value, MaintenanceStatus::InProgress->value])
                ->exists();

            if ($openExists) {
                throw new RuntimeException('Masih ada work order pemeliharaan terbuka untuk inventaris ini.');
            }

            $status = MaintenanceStatus::from($data['status'] ?? MaintenanceStatus::Waiting->value);

            $maintenance = Maintenance::query()->create([
                ...$data,
                'maintenance_number' => $this->nextNumber(),
                'status' => $status,
                'created_by' => $actor->id,
                'started_at' => $status === MaintenanceStatus::InProgress ? now() : null,
                'completed_at' => $status === MaintenanceStatus::Completed ? now() : null,
            ]);

            $this->recordStatusHistory($maintenance, null, $status, $actor, 'Work order dibuat');

            if ($status->isOpen() || $status === MaintenanceStatus::Completed) {
                $this->syncInventoryOnCreate($item, $status, $maintenance->result);
            }

            $this->storePhotos($maintenance, 'before', $beforePhotos);

            $this->audit->log(
                $actor,
                AuditAction::Create,
                $maintenance,
                null,
                $maintenance->only([
                    'maintenance_number', 'inventory_item_id', 'vendor_id', 'maintenance_type',
                    'priority', 'technician', 'service_date', 'status', 'cost', 'estimated_cost',
                ]),
                'MAINTENANCE_CREATED',
            );

            return $maintenance->fresh(['inventoryItem', 'vendor', 'photos']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $duringPhotos
     * @param  array<int, UploadedFile>  $afterPhotos
     */
    public function update(Maintenance $maintenance, array $data, User $actor, array $duringPhotos = [], array $afterPhotos = []): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $data, $actor, $duringPhotos, $afterPhotos) {
            $maintenance = Maintenance::query()->lockForUpdate()->findOrFail($maintenance->id);
            $oldStatus = $maintenance->status;
            $old = $maintenance->only([
                'vendor_id', 'service_date', 'next_schedule', 'status', 'cost', 'estimated_cost',
                'technician', 'priority', 'maintenance_type', 'result', 'parts_replaced', 'notes', 'description',
            ]);

            if (isset($data['status'])) {
                $data['status'] = $data['status'] instanceof MaintenanceStatus
                    ? $data['status']
                    : MaintenanceStatus::from((string) $data['status']);
            }

            if (isset($data['result']) && $data['result'] !== null && $data['result'] !== '') {
                $data['result'] = $data['result'] instanceof MaintenanceResult
                    ? $data['result']
                    : MaintenanceResult::from((string) $data['result']);
            } elseif (array_key_exists('result', $data) && ($data['result'] === null || $data['result'] === '')) {
                $data['result'] = null;
            }

            $newStatus = $data['status'] ?? $oldStatus;

            if ($newStatus === MaintenanceStatus::InProgress && ! $maintenance->started_at) {
                $data['started_at'] = now();
            }

            if ($newStatus === MaintenanceStatus::Completed) {
                $data['completed_at'] = $maintenance->completed_at ?? now();
                $result = $data['result'] ?? $maintenance->result;
                if (! $result instanceof MaintenanceResult) {
                    throw new RuntimeException('Hasil pemeliharaan wajib diisi saat status Selesai.');
                }
                $data['result'] = $result;
            }

            if ($newStatus === MaintenanceStatus::Cancelled) {
                $data['completed_at'] = $maintenance->completed_at ?? now();
            }

            $maintenance->update($data);
            $maintenance->refresh();

            if ($oldStatus !== $maintenance->status) {
                $this->recordStatusHistory(
                    $maintenance,
                    $oldStatus,
                    $maintenance->status,
                    $actor,
                    'Status diubah',
                );
                $this->applyInventoryStatus($maintenance);
            } elseif ($maintenance->status === MaintenanceStatus::Completed && ($old['result'] ?? null) !== ($maintenance->result?->value)) {
                $this->applyInventoryStatus($maintenance);
            }

            $this->storePhotos($maintenance, 'during', $duringPhotos);
            $this->storePhotos($maintenance, 'after', $afterPhotos);

            $code = match ($maintenance->status) {
                MaintenanceStatus::InProgress => 'MAINTENANCE_STARTED',
                MaintenanceStatus::Completed => 'MAINTENANCE_COMPLETED',
                MaintenanceStatus::Cancelled => 'MAINTENANCE_CANCELLED',
                default => 'MAINTENANCE_UPDATED',
            };

            $this->audit->log(
                $actor,
                AuditAction::Update,
                $maintenance,
                $old,
                $maintenance->only([
                    'vendor_id', 'service_date', 'next_schedule', 'status', 'cost', 'estimated_cost',
                    'technician', 'priority', 'maintenance_type', 'result', 'parts_replaced', 'notes',
                ]),
                $code,
            );

            return $maintenance->fresh(['inventoryItem', 'vendor', 'photos']);
        });
    }

    public function delete(Maintenance $maintenance, User $actor): void
    {
        DB::transaction(function () use ($maintenance, $actor) {
            $snapshot = $maintenance->only(['inventory_item_id', 'status', 'service_date', 'maintenance_number']);

            if ($maintenance->status?->isOpen()) {
                $this->restoreInventoryIfNoOpen($maintenance->inventory_item_id, $maintenance->id);
            }

            foreach ($maintenance->photos as $photo) {
                Storage::disk('public')->delete($photo->path);
            }

            $maintenance->delete();

            $this->audit->log($actor, AuditAction::Delete, null, $snapshot, null, 'MAINTENANCE_DELETED');
        });
    }

    /**
     * Asset card payload for scan / create screens.
     *
     * @return array<string, mixed>
     */
    public function assetCard(InventoryItem $item): array
    {
        $item->loadMissing([
            'category:id,code,name',
            'subCategory:id,code,name',
            'procurementType:id,code,name',
            'room.floor.building.campus',
            'photos' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id')->limit(1),
        ]);

        $room = $item->room;
        $floor = $room?->floor;
        $building = $floor?->building;
        $campus = $building?->campus;
        $primary = $item->photos->first();

        return [
            'id' => $item->id,
            'inventory_number' => $item->inventory_number,
            'name' => $item->name,
            'category' => $item->category?->name,
            'sub_category' => $item->subCategory?->name,
            'campus' => $campus?->name,
            'building' => $building?->name,
            'floor' => $floor?->name,
            'room' => $room?->name,
            'pic' => $item->responsible_person,
            'status' => $item->status?->value,
            'status_label' => $item->status?->label(),
            'purchase_year' => $item->purchase_year,
            'procurement_type' => $item->procurementType?->name,
            'photo_url' => $primary?->url,
            'borrowable' => $item->status?->isBorrowable() ?? false,
            'create_url' => route('maintenances.create', ['inventory_item_id' => $item->id]),
        ];
    }

    protected function syncInventoryOnCreate(InventoryItem $item, MaintenanceStatus $status, ?MaintenanceResult $result): void
    {
        if ($status === MaintenanceStatus::Completed) {
            $item->update(['status' => $this->inventoryStatusFromResult($result ?? MaintenanceResult::Repaired)]);

            return;
        }

        if ($status->isOpen()) {
            $item->update(['status' => InventoryStatus::Maintenance]);
        }
    }

    protected function applyInventoryStatus(Maintenance $maintenance): void
    {
        $itemId = $maintenance->inventory_item_id;

        if ($maintenance->status === MaintenanceStatus::Completed) {
            InventoryItem::query()->whereKey($itemId)->update([
                'status' => $this->inventoryStatusFromResult($maintenance->result ?? MaintenanceResult::Repaired),
            ]);

            return;
        }

        if ($maintenance->status === MaintenanceStatus::Cancelled) {
            $this->restoreInventoryIfNoOpen($itemId, $maintenance->id);

            return;
        }

        if ($maintenance->status->isOpen()) {
            InventoryItem::query()->whereKey($itemId)->update([
                'status' => InventoryStatus::Maintenance,
            ]);
        }
    }

    protected function inventoryStatusFromResult(MaintenanceResult $result): InventoryStatus
    {
        return match ($result) {
            MaintenanceResult::Repaired => InventoryStatus::Aktif,
            MaintenanceResult::CannotRepair => InventoryStatus::TidakDapatDiperbaiki,
            MaintenanceResult::NeedReplacement => InventoryStatus::PerluPenggantian,
        };
    }

    protected function restoreInventoryIfNoOpen(int $inventoryItemId, ?int $exceptMaintenanceId = null): void
    {
        $query = Maintenance::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->whereIn('status', [MaintenanceStatus::Waiting->value, MaintenanceStatus::InProgress->value]);

        if ($exceptMaintenanceId) {
            $query->where('id', '!=', $exceptMaintenanceId);
        }

        if ($query->exists()) {
            return;
        }

        InventoryItem::query()->whereKey($inventoryItemId)->update([
            'status' => InventoryStatus::Aktif,
        ]);
    }

    protected function recordStatusHistory(
        Maintenance $maintenance,
        ?MaintenanceStatus $from,
        MaintenanceStatus $to,
        User $actor,
        ?string $note = null,
    ): void {
        MaintenanceStatusHistory::query()->create([
            'maintenance_id' => $maintenance->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'changed_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    protected function storePhotos(Maintenance $maintenance, string $type, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store("maintenance/{$maintenance->id}/{$type}", 'public');
            MaintenancePhoto::query()->create([
                'maintenance_id' => $maintenance->id,
                'type' => $type,
                'path' => $path,
            ]);
        }
    }

    protected function nextNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "MNT-{$date}-";

        $last = Maintenance::query()
            ->where('maintenance_number', 'like', $prefix.'%')
            ->orderByDesc('maintenance_number')
            ->value('maintenance_number');

        $seq = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
