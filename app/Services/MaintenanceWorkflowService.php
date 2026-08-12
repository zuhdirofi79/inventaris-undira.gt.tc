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
        protected InventorySearchService $inventorySearch,
        protected NotificationService $notifications,
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
                ->whereIn('status', [
                    MaintenanceStatus::Waiting->value,
                    MaintenanceStatus::InProgress->value,
                    MaintenanceStatus::Revision->value,
                    MaintenanceStatus::PendingApproval->value,
                ])
                ->exists();

            if ($openExists) {
                throw new RuntimeException('Masih ada work order pemeliharaan terbuka untuk inventaris ini.');
            }

            $status = MaintenanceStatus::from($data['status'] ?? MaintenanceStatus::Waiting->value);

            if (! empty($data['technician_user_id']) && empty($data['technician'])) {
                $data['technician'] = User::query()->whereKey($data['technician_user_id'])->value('name');
            }

            $maintenance = Maintenance::query()->create([
                ...$data,
                'maintenance_number' => $this->nextNumber(),
                'status' => $status,
                'created_by' => $actor->id,
                'started_at' => in_array($status, [MaintenanceStatus::InProgress, MaintenanceStatus::Revision], true) ? now() : null,
                'completed_at' => $status === MaintenanceStatus::Completed ? now() : null,
            ]);

            $this->recordStatusHistory($maintenance, null, $status, $actor, 'Work order dibuat');

            if ($status->isOpen() || $status === MaintenanceStatus::PendingApproval || $status === MaintenanceStatus::Completed) {
                $this->syncInventoryOnCreate($item, $status, $maintenance->result);
            }

            $this->storePhotos($maintenance, 'before', $beforePhotos);

            $this->auditTechnicianAction(
                $actor,
                $maintenance,
                null,
                $status,
                'MAINTENANCE_CREATED',
                'Work order dibuat',
            );

            return $maintenance->fresh(['inventoryItem', 'vendor', 'photos', 'technicianUser']);
        });
    }

    /**
     * Mulai perbaikan dari scan QR — status inventaris: Dalam Perbaikan.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $beforePhotos
     */
    public function startRepair(array $data, User $actor, array $beforePhotos = []): Maintenance
    {
        $data['status'] = MaintenanceStatus::InProgress->value;
        $data['maintenance_type'] = $data['maintenance_type'] ?? \App\Enums\MaintenanceType::Repair->value;
        $data['priority'] = $data['priority'] ?? \App\Enums\MaintenancePriority::Medium->value;
        $data['technician_user_id'] = $data['technician_user_id'] ?? $actor->id;
        $data['technician'] = $data['technician'] ?? $actor->name;
        $data['service_date'] = $data['service_date'] ?? now()->toDateString();

        return $this->create($data, $actor, $beforePhotos);
    }

    /**
     * Teknisi menyelesaikan perbaikan → Menunggu Approval Admin (inventaris BELUM aktif).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $afterPhotos
     */
    public function submitForApproval(Maintenance $maintenance, array $data, User $actor, array $afterPhotos = []): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $data, $actor, $afterPhotos) {
            $maintenance = Maintenance::query()->lockForUpdate()->findOrFail($maintenance->id);

            if (! $maintenance->status?->isOpen()) {
                throw new RuntimeException('Hanya work order yang sedang dikerjakan yang dapat diajukan approval.');
            }

            $oldStatus = $maintenance->status;
            $oldInventory = $maintenance->inventoryItem?->status;

            $maintenance->update([
                'work_performed' => $data['work_performed'] ?? $maintenance->work_performed,
                'notes' => $data['notes'] ?? $maintenance->notes,
                'cost' => $data['cost'] ?? $maintenance->cost,
                'completed_at' => isset($data['completed_at'])
                    ? \Carbon\Carbon::parse($data['completed_at'])->endOfDay()
                    : now(),
                'result' => $data['result'] ?? MaintenanceResult::Repaired,
                'status' => MaintenanceStatus::PendingApproval,
                'submitted_for_approval_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->storePhotos($maintenance, 'after', $afterPhotos);
            $this->recordStatusHistory($maintenance, $oldStatus, MaintenanceStatus::PendingApproval, $actor, 'Diajukan approval admin');

            InventoryItem::query()->whereKey($maintenance->inventory_item_id)->update([
                'status' => InventoryStatus::MenungguApproval,
            ]);

            $this->auditTechnicianAction(
                $actor,
                $maintenance->fresh(),
                $oldStatus,
                MaintenanceStatus::PendingApproval,
                'MAINTENANCE_PENDING_APPROVAL',
                'Perbaikan diajukan ke admin',
                $oldInventory?->value,
                InventoryStatus::MenungguApproval->value,
            );

            $fresh = $maintenance->fresh(['inventoryItem', 'vendor', 'photos', 'technicianUser']);
            $this->notifications->notifyMaintenanceApproval($fresh, 'submitted');

            return $fresh;
        });
    }

    /**
     * Admin approve → inventaris Aktif (atau sesuai result).
     */
    public function approve(Maintenance $maintenance, User $actor, ?string $note = null): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $actor, $note) {
            $maintenance = Maintenance::query()->lockForUpdate()->findOrFail($maintenance->id);

            if ($maintenance->status !== MaintenanceStatus::PendingApproval) {
                throw new RuntimeException('Work order tidak dalam status menunggu approval.');
            }

            $oldStatus = $maintenance->status;
            $oldInventory = $maintenance->inventoryItem?->status;
            $result = $maintenance->result ?? MaintenanceResult::Repaired;
            $newInventory = $this->inventoryStatusFromResult($result);

            $maintenance->update([
                'status' => MaintenanceStatus::Completed,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_note' => $note,
                'completed_at' => $maintenance->completed_at ?? now(),
            ]);

            $this->recordStatusHistory($maintenance, $oldStatus, MaintenanceStatus::Completed, $actor, $note ?: 'Disetujui admin');

            InventoryItem::query()->whereKey($maintenance->inventory_item_id)->update([
                'status' => $newInventory,
            ]);

            $this->auditTechnicianAction(
                $actor,
                $maintenance->fresh(),
                $oldStatus,
                MaintenanceStatus::Completed,
                'MAINTENANCE_APPROVED',
                $note ?: 'Approval disetujui',
                $oldInventory?->value,
                $newInventory->value,
            );

            $fresh = $maintenance->fresh(['inventoryItem', 'approver', 'technicianUser', 'creator']);
            $this->notifications->notifyMaintenanceApproval($fresh, 'approved');

            return $fresh;
        });
    }

    /**
     * Admin tolak → inventaris Revisi Perbaikan + notifikasi teknisi.
     */
    public function reject(Maintenance $maintenance, User $actor, string $reason): Maintenance
    {
        $fresh = DB::transaction(function () use ($maintenance, $actor, $reason) {
            $maintenance = Maintenance::query()->lockForUpdate()->findOrFail($maintenance->id);

            if ($maintenance->status !== MaintenanceStatus::PendingApproval) {
                throw new RuntimeException('Work order tidak dalam status menunggu approval.');
            }

            $oldStatus = $maintenance->status;
            $oldInventory = $maintenance->inventoryItem?->status;

            $maintenance->update([
                'status' => MaintenanceStatus::Revision,
                'rejection_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
                'approval_note' => null,
                'submitted_for_approval_at' => null,
            ]);

            $this->recordStatusHistory($maintenance, $oldStatus, MaintenanceStatus::Revision, $actor, $reason);

            InventoryItem::query()->whereKey($maintenance->inventory_item_id)->update([
                'status' => InventoryStatus::RevisiPerbaikan,
            ]);

            $this->auditTechnicianAction(
                $actor,
                $maintenance->fresh(),
                $oldStatus,
                MaintenanceStatus::Revision,
                'MAINTENANCE_REJECTED',
                $reason,
                $oldInventory?->value,
                InventoryStatus::RevisiPerbaikan->value,
            );

            return $maintenance->fresh(['inventoryItem', 'technicianUser', 'creator']);
        });

        // Notify after commit so a notification failure cannot roll back the status change.
        $this->notifications->notifyMaintenanceApproval($fresh, 'rejected', $reason);

        return $fresh;
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

            // Teknisi tidak boleh langsung Completed — harus lewat approval admin.
            if ($newStatus === MaintenanceStatus::Completed && ! $actor->can('maintenance.approve')) {
                return $this->submitForApproval(
                    $maintenance,
                    [
                        'work_performed' => $data['work_performed'] ?? $data['description'] ?? $maintenance->work_performed,
                        'notes' => $data['notes'] ?? $maintenance->notes,
                        'cost' => $data['cost'] ?? $maintenance->cost,
                        'result' => $data['result'] ?? MaintenanceResult::Repaired,
                    ],
                    $actor,
                    $afterPhotos,
                );
            }

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
            $oldStatus = $maintenance->status;

            if ($maintenance->status?->isOpen() || $maintenance->status === MaintenanceStatus::PendingApproval) {
                $this->restoreInventoryIfNoOpen($maintenance->inventory_item_id, $maintenance->id);
            }

            // Soft delete — histori & foto tetap tersimpan.
            $maintenance->delete();

            $this->auditTechnicianAction(
                $actor,
                $maintenance,
                $oldStatus,
                null,
                'MAINTENANCE_SOFT_DELETED',
                'Work order diarsipkan (soft delete)',
            );

            $this->audit->log($actor, AuditAction::Delete, $maintenance, $snapshot, null, 'MAINTENANCE_DELETED');
        });
    }

    /**
     * Asset card for scan / create — thin wrapper over InventorySearchService (no duplicated query).
     *
     * @return array<string, mixed>
     */
    public function assetCard(InventoryItem $item): array
    {
        $ctx = $this->inventorySearch->maintenanceContext($item->id);

        return $ctx
            ? $this->assetCardFromContext($ctx)
            : [
                'id' => $item->id,
                'inventory_number' => $item->inventory_number,
                'name' => $item->name,
                'create_url' => route('maintenances.create', ['inventory_item_id' => $item->id]),
                'start_repair_url' => route('maintenances.start-repair', ['inventory_item_id' => $item->id]),
            ];
    }

    /**
     * Map maintenance-context payload to scan/create card shape (no extra DB hit).
     *
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function assetCardFromContext(array $ctx): array
    {
        return [
            'id' => $ctx['id'],
            'inventory_number' => $ctx['inventory_number'],
            'name' => $ctx['name'],
            'category' => $ctx['category_name'] ?? $ctx['category'] ?? null,
            'sub_category' => $ctx['sub_category_name'] ?? $ctx['sub_category'] ?? null,
            'campus' => $ctx['campus_name'] ?? $ctx['campus'] ?? null,
            'building' => $ctx['building_name'] ?? $ctx['building'] ?? null,
            'floor' => $ctx['floor_name'] ?? $ctx['floor'] ?? null,
            'room' => $ctx['room_name'] ?? $ctx['room'] ?? null,
            'location' => $ctx['location'] ?? null,
            'pic' => (($ctx['pic'] ?? null) === '—') ? null : ($ctx['pic'] ?? null),
            'status' => $ctx['status'] ?? null,
            'status_label' => $ctx['status_label'] ?? null,
            'condition_label' => $ctx['condition_label'] ?? null,
            'warranty_label' => $ctx['warranty_label'] ?? null,
            'purchase_year' => $ctx['purchase_year'] ?? null,
            'procurement_type' => (($ctx['procurement_type'] ?? null) === '—') ? null : ($ctx['procurement_type'] ?? null),
            'photo_url' => $ctx['photo_url'] ?? null,
            'borrowable' => $ctx['borrowable'] ?? false,
            'create_url' => $ctx['create_url'] ?? route('maintenances.create', ['inventory_item_id' => $ctx['id']]),
            'start_repair_url' => route('maintenances.start-repair', ['inventory_item_id' => $ctx['id']]),
            'maintenance_history' => $ctx['maintenance_history'] ?? [],
            'maintenance_schedules' => $ctx['maintenance_schedules'] ?? [],
            'latest_work_order' => $ctx['latest_work_order'] ?? null,
        ];
    }

    protected function syncInventoryOnCreate(InventoryItem $item, MaintenanceStatus $status, ?MaintenanceResult $result): void
    {
        if ($status === MaintenanceStatus::Completed) {
            $item->update(['status' => $this->inventoryStatusFromResult($result ?? MaintenanceResult::Repaired)]);

            return;
        }

        if ($status === MaintenanceStatus::PendingApproval) {
            $item->update(['status' => InventoryStatus::MenungguApproval]);

            return;
        }

        if ($status === MaintenanceStatus::Revision) {
            $item->update(['status' => InventoryStatus::RevisiPerbaikan]);

            return;
        }

        if ($status->isOpen()) {
            $item->update(['status' => InventoryStatus::Maintenance]);
        }
    }

    protected function applyInventoryStatus(Maintenance $maintenance): void
    {
        $itemId = $maintenance->inventory_item_id;

        if ($maintenance->status === MaintenanceStatus::PendingApproval) {
            InventoryItem::query()->whereKey($itemId)->update([
                'status' => InventoryStatus::MenungguApproval,
            ]);

            return;
        }

        if ($maintenance->status === MaintenanceStatus::Revision) {
            InventoryItem::query()->whereKey($itemId)->update([
                'status' => InventoryStatus::RevisiPerbaikan,
            ]);

            return;
        }

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
            ->whereIn('status', [
                MaintenanceStatus::Waiting->value,
                MaintenanceStatus::InProgress->value,
                MaintenanceStatus::Revision->value,
                MaintenanceStatus::PendingApproval->value,
            ]);

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
     * Audit trail lengkap aktivitas teknisi/admin pada maintenance.
     */
    protected function auditTechnicianAction(
        User $actor,
        Maintenance $maintenance,
        ?MaintenanceStatus $oldStatus,
        ?MaintenanceStatus $newStatus,
        string $code,
        ?string $note = null,
        ?string $oldInventoryStatus = null,
        ?string $newInventoryStatus = null,
    ): void {
        $this->audit->log(
            $actor,
            $oldStatus ? AuditAction::Update : AuditAction::Create,
            $maintenance,
            [
                'maintenance_status' => $oldStatus?->value,
                'inventory_status' => $oldInventoryStatus,
            ],
            [
                'maintenance_status' => $newStatus?->value ?? $maintenance->status?->value,
                'inventory_status' => $newInventoryStatus,
                'maintenance_number' => $maintenance->maintenance_number,
                'note' => $note,
                'user_name' => $actor->name,
                'role' => $actor->getRoleNames()->implode(','),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'occurred_at' => now()->toDateTimeString(),
            ],
            $code,
            reason: $note,
        );
    }

    /**
     * Append photos for a WO stage (does not remove existing photos of other/same type).
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return int Number of files stored
     */
    public function addPhotos(Maintenance $maintenance, string $type, array|UploadedFile|null $files): int
    {
        return $this->storePhotos($maintenance, $type, $files);
    }

    public function deletePhoto(Maintenance $maintenance, MaintenancePhoto $photo, User $actor): void
    {
        if ((int) $photo->maintenance_id !== (int) $maintenance->id) {
            throw new RuntimeException('Foto tidak terkait dengan work order ini.');
        }

        $path = $photo->path;
        $photoId = $photo->id;
        $type = $photo->type;

        if (filled($path)) {
            Storage::disk('public')->delete(ltrim(str_replace('\\', '/', (string) $path), '/'));
        }
        $photo->delete();

        $this->audit->log(
            $actor,
            AuditAction::Update,
            $maintenance,
            ['photo_id' => $photoId, 'type' => $type, 'path' => $path],
            null,
            'MAINTENANCE_PHOTO_DELETED',
        );
    }

    /**
     * Store photos for a WO stage (append). Empty upload keeps existing photos.
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return int
     */
    protected function storePhotos(Maintenance $maintenance, string $type, array|UploadedFile|null $files): int
    {
        $files = $this->normalizeUploadedFiles($files);
        if ($files === []) {
            return 0;
        }

        $dir = sprintf(
            'maintenance/%s/%s/%d/%s',
            now()->format('Y'),
            now()->format('m'),
            $maintenance->id,
            $type
        );

        $stored = 0;
        foreach ($files as $file) {
            $storedPath = $file->store($dir, 'public');
            if (! is_string($storedPath) || $storedPath === '') {
                continue;
            }

            // Normalize Windows separators so public URLs stay valid.
            $path = str_replace('\\', '/', $storedPath);

            MaintenancePhoto::query()->create([
                'maintenance_id' => $maintenance->id,
                'type' => $type,
                'path' => $path,
            ]);
            $stored++;
        }

        return $stored;
    }

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return array<int, UploadedFile>
     */
    protected function normalizeUploadedFiles(array|UploadedFile|null $files): array
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            static fn ($file) => $file instanceof UploadedFile && $file->isValid()
        ));
    }

    protected function nextNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "MNT-{$date}-";

        $last = Maintenance::withTrashed()
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
