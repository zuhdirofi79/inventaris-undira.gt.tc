<?php

namespace App\Services;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Models\Borrowing;
use App\Models\DeletionApproval;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\RoomBorrowing;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\VehicleBorrowing;
use App\Notifications\ApprovalCancelledNotification;
use App\Notifications\BorrowingApprovedNotification;
use App\Notifications\BorrowingCreatedNotification;
use App\Notifications\BorrowingRejectedNotification;
use App\Notifications\DeletionApprovalNotification;
use App\Notifications\FacilityRequestSubmittedNotification;
use App\Notifications\MaintenanceApprovalNotification;
use App\Notifications\MaintenanceDueNotification;
use App\Notifications\MaintenanceScheduleReminderNotification;
use App\Notifications\OperationalBorrowingNotification;
use App\Notifications\OverdueBorrowingNotification;
use App\Notifications\StockOpnameStatusNotification;
use App\Notifications\WarrantyExpiringNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Roles that receive operational alerts.
     *
     * @var list<string>
     */
    private const OPERATOR_ROLES = ['super-admin', 'admin', 'staff', 'teknisi'];

    public function notifyWarrantyExpiring(User $user, InventoryItem $item, int $daysRemaining): bool
    {
        if ($this->alreadyNotified($user, WarrantyExpiringNotification::class, 'inventory_item_id', $item->id)) {
            return false;
        }

        $user->notify(new WarrantyExpiringNotification($item, $daysRemaining));

        return true;
    }

    public function notifyMaintenanceDue(User $user, Maintenance $maintenance): bool
    {
        if ($this->alreadyNotified($user, MaintenanceDueNotification::class, 'maintenance_id', $maintenance->id)) {
            return false;
        }

        $user->notify(new MaintenanceDueNotification($maintenance));

        return true;
    }

    public function notifyMaintenanceScheduleReminder(User $user, MaintenanceSchedule $schedule): bool
    {
        if ($this->alreadyNotified(
            $user,
            MaintenanceScheduleReminderNotification::class,
            'maintenance_schedule_id',
            $schedule->id,
            withinDays: 1,
        )) {
            return false;
        }

        $user->notify(new MaintenanceScheduleReminderNotification($schedule));

        return true;
    }

    public function notifyOverdueBorrowing(User $user, Borrowing $borrowing): bool
    {
        if ($this->alreadyNotified($user, OverdueBorrowingNotification::class, 'borrowing_id', $borrowing->id)) {
            return false;
        }

        $user->notify(new OverdueBorrowingNotification($borrowing));

        return true;
    }

    public function notifyBorrowingCreated(Borrowing $borrowing): int
    {
        $borrowing->loadMissing(['inventoryItem', 'items.inventoryItem']);
        $count = 0;

        foreach ($this->usersWithPermission('borrowing.approve') as $user) {
            if (! $user instanceof User || ! $user->is_active) {
                continue;
            }

            if ($this->alreadyNotified($user, BorrowingCreatedNotification::class, 'borrowing_id', $borrowing->id)) {
                continue;
            }

            $user->notify(new BorrowingCreatedNotification($borrowing));
            $count++;
        }

        $count += $this->notifyInventoryOpsRoles($borrowing, 'submitted');

        return $count;
    }

    public function notifyBorrowingApproved(Borrowing $borrowing): int
    {
        $borrowing->loadMissing(['requestedBy', 'inventoryItem', 'items.inventoryItem']);
        $count = 0;
        $user = $borrowing->requestedBy;

        if ($user instanceof User && $user->is_active) {
            if (! $this->alreadyNotified($user, BorrowingApprovedNotification::class, 'borrowing_id', $borrowing->id)) {
                $user->notify(new BorrowingApprovedNotification($borrowing));
                $count++;
            }
        }

        $count += $this->notifyInventoryOpsRoles($borrowing, 'approved');

        return $count;
    }

    public function notifyBorrowingRejected(Borrowing $borrowing): int
    {
        $borrowing->loadMissing(['requestedBy', 'inventoryItem', 'items.inventoryItem']);
        $count = 0;
        $user = $borrowing->requestedBy;

        if ($user instanceof User && $user->is_active) {
            if (! $this->alreadyNotified($user, BorrowingRejectedNotification::class, 'borrowing_id', $borrowing->id)) {
                $user->notify(new BorrowingRejectedNotification($borrowing));
                $count++;
            }
        }

        $count += $this->notifyInventoryOpsRoles($borrowing, 'rejected');

        return $count;
    }

    public function notifyRoomBorrowingSubmitted(RoomBorrowing $borrowing): int
    {
        $count = $this->notifyFacilityApprovers(
            permission: 'room-borrowing.approve',
            referenceKey: 'room_borrowing_id',
            referenceId: $borrowing->id,
            payload: $this->roomApproverPayload($borrowing),
        );

        $count += $this->notifyRoleBorrowingEvent(
            roles: ['ob'],
            referenceKey: 'room_borrowing_id',
            referenceId: $borrowing->id,
            event: 'submitted',
            payload: $this->roomPayload($borrowing, 'submitted'),
        );

        return $count;
    }

    public function notifyRoomBorrowingDecided(RoomBorrowing $borrowing): int
    {
        $event = $borrowing->status === FacilityRequestStatus::Disetujui ? 'approved' : 'rejected';

        return $this->notifyRoleBorrowingEvent(
            roles: ['ob'],
            referenceKey: 'room_borrowing_id',
            referenceId: $borrowing->id,
            event: $event,
            payload: $this->roomPayload($borrowing, $event),
        );
    }

    public function notifyVehicleBorrowingSubmitted(VehicleBorrowing $borrowing): int
    {
        $count = $this->notifyFacilityApprovers(
            permission: 'vehicle-borrowing.approve',
            referenceKey: 'vehicle_borrowing_id',
            referenceId: $borrowing->id,
            payload: $this->vehicleApproverPayload($borrowing),
        );

        $count += $this->notifyRoleBorrowingEvent(
            roles: ['driver'],
            referenceKey: 'vehicle_borrowing_id',
            referenceId: $borrowing->id,
            event: 'submitted',
            payload: $this->vehiclePayload($borrowing, 'submitted'),
        );

        return $count;
    }

    /**
     * In-app alerts for users who can approve facility borrowings (Admin / Super Admin).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function notifyFacilityApprovers(
        string $permission,
        string $referenceKey,
        int $referenceId,
        array $payload,
    ): int {
        $count = 0;

        foreach ($this->usersWithPermission($permission) as $user) {
            if (! $user instanceof User || ! $user->is_active) {
                continue;
            }

            if ($this->alreadyNotified($user, FacilityRequestSubmittedNotification::class, $referenceKey, $referenceId)) {
                continue;
            }

            $user->notify(new FacilityRequestSubmittedNotification($payload + [
                'reference_key' => $referenceKey,
                'reference_id' => $referenceId,
                'event' => 'submitted',
            ]));
            $count++;
        }

        return $count;
    }

    public function notifyVehicleBorrowingDecided(VehicleBorrowing $borrowing): int
    {
        $borrowing->loadMissing(['requestedBy', 'driver.user', 'vehicle', 'campus', 'route', 'approvedBy']);
        $event = $borrowing->status === FacilityRequestStatus::Disetujui ? 'approved' : 'rejected';
        $count = 0;

        // Borrower (requester account) — approve and reject.
        $requester = $borrowing->requestedBy;
        if ($requester instanceof User && $requester->is_active) {
            if (! $this->alreadyNotifiedForEvent($requester, 'vehicle_borrowing_id', $borrowing->id, $event)) {
                $requester->notify(new OperationalBorrowingNotification($this->vehiclePayload($borrowing, $event) + [
                    'reference_key' => 'vehicle_borrowing_id',
                    'reference_id' => $borrowing->id,
                    'event' => $event,
                ]));
                $count++;
            }
        }

        // Assigned driver only when approved with an internal fleet assignment.
        if ($event === 'approved' && $borrowing->assigned_driver_id) {
            $driverUser = $borrowing->driver?->user;
            if ($driverUser instanceof User && $driverUser->is_active) {
                if (! $this->alreadyNotifiedForEvent($driverUser, 'vehicle_borrowing_id', $borrowing->id, 'driver_assigned')) {
                    $driverUser->notify(new OperationalBorrowingNotification($this->vehicleDriverAssignmentPayload($borrowing) + [
                        'reference_key' => 'vehicle_borrowing_id',
                        'reference_id' => $borrowing->id,
                        'event' => 'driver_assigned',
                    ]));
                    $count++;
                }
            }
        }

        return $count;
    }

    public function notifyBorrowingApprovalCancelled(Borrowing $borrowing): int
    {
        $borrowing->loadMissing(['requestedBy', 'cancelledBy', 'items.inventoryItem', 'inventoryItem']);
        $count = 0;
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $payload = [
            'type' => 'borrowing_approval_cancelled',
            'kind' => 'inventaris',
            'title' => 'Persetujuan Inventaris Dibatalkan',
            'message' => 'Persetujuan peminjaman inventaris dibatalkan. Alasan: '
                .($borrowing->cancellation_reason ?: '—'),
            'borrowing_id' => $borrowing->id,
            'approval_number' => $borrowing->approval_number,
            'cancelled_at' => $borrowing->cancelled_at?->timezone($tz)->format('d/m/Y H:i'),
            'cancelled_by' => $borrowing->cancelledBy?->name,
            'cancellation_reason' => $borrowing->cancellation_reason,
            'status' => $borrowing->status?->label(),
            'url' => route('borrowings.show', $borrowing, false),
        ];

        $user = $borrowing->requestedBy;
        if ($user instanceof User && $user->is_active) {
            if (! $this->alreadyNotified($user, ApprovalCancelledNotification::class, 'borrowing_id', $borrowing->id)) {
                $user->notify(new ApprovalCancelledNotification($payload));
                $count++;
            }
        }

        $count += $this->notifyInventoryOpsRoles($borrowing, 'cancelled');

        return $count;
    }

    public function notifyRoomBorrowingApprovalCancelled(RoomBorrowing $borrowing): int
    {
        $borrowing->loadMissing(['requestedBy', 'cancelledBy', 'room', 'campus']);
        $count = 0;
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $payload = [
            'type' => 'room_borrowing_approval_cancelled',
            'kind' => 'ruangan',
            'title' => 'Persetujuan Ruangan Dibatalkan',
            'message' => 'Persetujuan peminjaman ruangan dibatalkan. Alasan: '
                .($borrowing->cancellation_reason ?: '—'),
            'room_borrowing_id' => $borrowing->id,
            'approval_number' => $borrowing->approval_number,
            'cancelled_at' => $borrowing->cancelled_at?->timezone($tz)->format('d/m/Y H:i'),
            'cancelled_by' => $borrowing->cancelledBy?->name,
            'cancellation_reason' => $borrowing->cancellation_reason,
            'status' => $borrowing->status?->label(),
            'url' => route('room-borrowings.show', $borrowing, false),
        ];

        $user = $borrowing->requestedBy;
        if ($user instanceof User && $user->is_active) {
            if (! $this->alreadyNotified($user, ApprovalCancelledNotification::class, 'room_borrowing_id', $borrowing->id)) {
                $user->notify(new ApprovalCancelledNotification($payload));
                $count++;
            }
        }

        $count += $this->notifyRoleBorrowingEvent(
            roles: ['ob'],
            referenceKey: 'room_borrowing_id',
            referenceId: $borrowing->id,
            event: 'cancelled',
            payload: $this->roomPayload($borrowing, 'cancelled'),
        );

        return $count;
    }

    public function notifyVehicleBorrowingApprovalCancelled(VehicleBorrowing $borrowing): int
    {
        $borrowing->loadMissing(['requestedBy', 'cancelledBy', 'campus', 'route']);
        $count = 0;
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $payload = [
            'type' => 'vehicle_borrowing_approval_cancelled',
            'kind' => 'kendaraan',
            'title' => 'Persetujuan Kendaraan Dibatalkan',
            'message' => 'Persetujuan peminjaman kendaraan dibatalkan. Alasan: '
                .($borrowing->cancellation_reason ?: '—'),
            'vehicle_borrowing_id' => $borrowing->id,
            'approval_number' => $borrowing->approval_number,
            'cancelled_at' => $borrowing->cancelled_at?->timezone($tz)->format('d/m/Y H:i'),
            'cancelled_by' => $borrowing->cancelledBy?->name,
            'cancellation_reason' => $borrowing->cancellation_reason,
            'status' => $borrowing->status?->label(),
            'url' => route('vehicle-borrowings.show', $borrowing, false),
        ];

        $user = $borrowing->requestedBy;
        if ($user instanceof User && $user->is_active) {
            if (! $this->alreadyNotified($user, ApprovalCancelledNotification::class, 'vehicle_borrowing_id', $borrowing->id)) {
                $user->notify(new ApprovalCancelledNotification($payload));
                $count++;
            }
        }

        $count += $this->notifyRoleBorrowingEvent(
            roles: ['driver'],
            referenceKey: 'vehicle_borrowing_id',
            referenceId: $borrowing->id,
            event: 'cancelled',
            payload: $this->vehiclePayload($borrowing, 'cancelled'),
        );

        return $count;
    }

    /**
     * OB + Teknisi inventory operational alerts.
     */
    protected function notifyInventoryOpsRoles(Borrowing $borrowing, string $event): int
    {
        $count = 0;
        $count += $this->notifyRoleBorrowingEvent(
            roles: ['ob'],
            referenceKey: 'borrowing_id',
            referenceId: $borrowing->id,
            event: 'ob_'.$event,
            payload: $this->inventoryPayload($borrowing, $event, 'ob'),
        );

        if ($event === 'submitted') {
            $count += $this->notifyRoleBorrowingEvent(
                roles: ['teknisi'],
                referenceKey: 'borrowing_id',
                referenceId: $borrowing->id,
                event: 'teknisi_submitted',
                payload: $this->inventoryPayload($borrowing, 'submitted', 'teknisi'),
            );
        }

        if ($event === 'approved') {
            $count += $this->notifyRoleBorrowingEvent(
                roles: ['teknisi'],
                referenceKey: 'borrowing_id',
                referenceId: $borrowing->id,
                event: 'teknisi_prep',
                payload: $this->inventoryPayload($borrowing, 'prep', 'teknisi'),
            );
        }

        if ($event === 'rejected') {
            $count += $this->notifyRoleBorrowingEvent(
                roles: ['teknisi'],
                referenceKey: 'borrowing_id',
                referenceId: $borrowing->id,
                event: 'teknisi_rejected',
                payload: $this->inventoryPayload($borrowing, 'rejected', 'teknisi'),
            );
        }

        return $count;
    }

    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $payload
     */
    protected function notifyRoleBorrowingEvent(
        array $roles,
        string $referenceKey,
        int $referenceId,
        string $event,
        array $payload,
    ): int {
        $count = 0;

        foreach ($this->activeUsersWithRoles($roles) as $user) {
            $exists = $user->notifications()
                ->where('type', OperationalBorrowingNotification::class)
                ->where('created_at', '>=', now()->subDay())
                ->where("data->{$referenceKey}", $referenceId)
                ->where('data->event', $event)
                ->exists();

            if ($exists) {
                continue;
            }

            $user->notify(new OperationalBorrowingNotification($payload + [
                'reference_key' => $referenceKey,
                'reference_id' => $referenceId,
                'event' => $event,
            ]));
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<string>  $roles
     * @return Collection<int, User>
     */
    protected function activeUsersWithRoles(array $roles): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->role($roles)
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function inventoryPayload(Borrowing $borrowing, string $event, string $audience): array
    {
        $borrowing->loadMissing(['items.inventoryItem', 'inventoryItem']);
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $schedule = ($borrowing->borrow_date?->timezone($tz)->format('d/m/Y H:i') ?: '—')
            .' – '.($borrowing->expected_return_date?->timezone($tz)->format('d/m/Y H:i') ?: '—');

        $approvedStatuses = [
            BorrowingItemStatus::Disetujui,
            BorrowingItemStatus::Dipinjam,
            BorrowingItemStatus::Dikembalikan,
        ];
        $lines = $borrowing->lineItems();
        $approved = $lines->filter(fn ($line) => in_array($line->status, $approvedStatuses, true))->values();
        $names = ($event === 'prep' ? $approved : $lines)
            ->map(fn ($line) => $line->inventoryItem?->name)
            ->filter()
            ->values();
        $first = (string) ($names->first() ?: ($borrowing->inventoryItem?->name ?: 'Inventaris'));
        $summary = $names->count() > 1 ? $first.' + '.($names->count() - 1).' item lainnya' : $first;

        // Teknisi has no borrowing.view — keep links on the in-app notification center.
        $safeUrl = $audience === 'teknisi'
            ? route('notifications.index', [], false)
            : ($event === 'submitted'
                ? route('borrowing-records.index', [], false)
                : route('borrowings.show', $borrowing, false));

        if ($audience === 'teknisi' && $event === 'prep') {
            if ($approved->isEmpty()) {
                return [
                    'type' => 'inventory_prep_skipped',
                    'kind' => 'inventaris',
                    'title' => 'Tidak Ada Inventaris untuk Disiapkan',
                    'message' => 'Pengajuan #'.$borrowing->id.' tidak memerlukan persiapan — tidak ada item yang disetujui.',
                    'subject' => $borrowing->approval_number ?: '#'.$borrowing->id,
                    'subtitle' => $borrowing->borrower_name,
                    'summary' => 'Tidak ada item disetujui',
                    'url' => $safeUrl,
                    'borrowing_id' => $borrowing->id,
                    'mail_subject' => '[LOADOUT] Inventaris — tidak ada item untuk disiapkan',
                    'mail_lines' => [
                        'Pengajuan inventaris tidak memerlukan persiapan karena tidak ada item yang disetujui.',
                        'Nomor: '.($borrowing->approval_number ?: '#'.$borrowing->id),
                        'Peminjam: '.$borrowing->borrower_name,
                    ],
                ];
            }

            $itemLines = $approved->map(function ($line) {
                $item = $line->inventoryItem;

                return trim(($item?->inventory_number ?: '—').' — '.($item?->name ?: 'Item'));
            })->all();

            return [
                'type' => 'inventory_prep',
                'kind' => 'inventaris',
                'title' => 'Persiapan Inventaris Disetujui',
                'message' => 'Siapkan '.$approved->count().' inventaris yang disetujui untuk '.$borrowing->borrower_name.'.',
                'subject' => $borrowing->approval_number ?: '#'.$borrowing->id,
                'subtitle' => $summary,
                'summary' => $schedule,
                'url' => $safeUrl,
                'borrowing_id' => $borrowing->id,
                'approved_count' => $approved->count(),
                'mail_subject' => '[LOADOUT] Persiapan inventaris yang disetujui',
                'mail_lines' => array_merge([
                    'Berikut inventaris yang benar-benar disetujui Admin dan perlu disiapkan:',
                    'Peminjam: '.$borrowing->borrower_name,
                    'Jadwal: '.$schedule,
                    'Item:',
                ], $itemLines),
            ];
        }

        $pendingNote = match ($event) {
            'submitted' => 'Status saat ini: menunggu persetujuan Admin. Belum boleh diserahkan.',
            'approved' => 'Admin telah menyetujui pengajuan inventaris.',
            'cancelled' => 'Admin membatalkan persetujuan peminjaman inventaris.',
            default => 'Admin menolak pengajuan inventaris.',
        };

        return [
            'type' => 'inventory_'.$event,
            'kind' => 'inventaris',
            'title' => match ($event) {
                'submitted' => 'Pengajuan Peminjaman Inventaris Baru',
                'approved' => 'Peminjaman Inventaris Disetujui',
                'cancelled' => 'Persetujuan Inventaris Dibatalkan',
                default => 'Peminjaman Inventaris Ditolak',
            },
            'message' => $pendingNote.' Ringkasan: '.$summary.'.',
            'subject' => $borrowing->approval_number ?: '#'.$borrowing->id,
            'subtitle' => $borrowing->borrower_name,
            'summary' => $schedule,
            'url' => $safeUrl,
            'borrowing_id' => $borrowing->id,
            'mail_subject' => '[LOADOUT] '.$pendingNote,
            'mail_lines' => [
                $pendingNote,
                'Nomor: '.($borrowing->approval_number ?: '#'.$borrowing->id),
                'Peminjam: '.$borrowing->borrower_name,
                'Detail: '.$summary,
                'Jadwal: '.$schedule,
                'Kampus: '.$borrowing->campusSummary(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function roomApproverPayload(RoomBorrowing $borrowing): array
    {
        $borrowing->loadMissing(['room.floor.building.campus', 'campus']);
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $schedule = ($borrowing->starts_at?->timezone($tz)->format('d/m/Y H:i') ?: '—')
            .' – '.($borrowing->ends_at?->timezone($tz)->format('d/m/Y H:i') ?: '—');
        $campus = $borrowing->campus?->name
            ?: $borrowing->room?->floor?->building?->campus?->name
            ?: '—';
        $roomName = $borrowing->room?->name ?: 'Ruangan';
        $activity = $borrowing->activityTypeLabel();

        return [
            'type' => 'room_submitted',
            'kind' => 'ruangan',
            'title' => 'Peminjaman Ruangan Baru',
            'message' => $borrowing->borrower_name
                .' mengajukan peminjaman ruang '.$roomName
                .' untuk kegiatan '.$activity
                .' di '.$campus.'. Status: Menunggu Persetujuan. Jadwal: '.$schedule.'.',
            'subject' => '#'.$borrowing->id,
            'subtitle' => $roomName.' · '.$activity,
            'summary' => $schedule,
            'campus' => $campus,
            'url' => route('room-borrowings.show', $borrowing, false),
            'room_borrowing_id' => $borrowing->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function roomPayload(RoomBorrowing $borrowing, string $event): array
    {
        $borrowing->loadMissing(['room.floor.building.campus', 'campus']);
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $schedule = ($borrowing->starts_at?->timezone($tz)->format('d/m/Y H:i') ?: '—')
            .' – '.($borrowing->ends_at?->timezone($tz)->format('d/m/Y H:i') ?: '—');
        $location = collect([
            $borrowing->campus?->name ?: $borrowing->room?->floor?->building?->campus?->name,
            $borrowing->room?->floor?->building?->name,
            $borrowing->room?->floor?->name,
            $borrowing->room?->name,
        ])->filter()->implode(' · ');

        $statusLabel = match ($event) {
            'submitted' => 'Menunggu persetujuan Admin',
            'approved' => 'Disetujui',
            'cancelled' => 'Persetujuan dibatalkan',
            default => 'Ditolak',
        };

        return [
            'type' => 'room_'.$event,
            'kind' => 'ruangan',
            'title' => match ($event) {
                'submitted' => 'Pengajuan Peminjaman Ruangan Baru',
                'approved' => 'Peminjaman Ruangan Disetujui',
                'cancelled' => 'Persetujuan Ruangan Dibatalkan',
                default => 'Peminjaman Ruangan Ditolak',
            },
            'message' => $statusLabel.'. '.$borrowing->borrower_name.' — '.($borrowing->room?->name ?: 'Ruangan').'.',
            'subject' => $borrowing->approval_number ?: '#'.$borrowing->id,
            'subtitle' => $borrowing->room?->name,
            'summary' => $schedule,
            'url' => route('room-borrowings.show', $borrowing, false),
            'room_borrowing_id' => $borrowing->id,
            'mail_subject' => '[LOADOUT] '.$statusLabel.' — peminjaman ruangan',
            'mail_lines' => [
                $statusLabel,
                'Nomor: '.($borrowing->approval_number ?: '#'.$borrowing->id),
                'Peminjam: '.$borrowing->borrower_name,
                'Lokasi: '.($location !== '' ? $location : '—'),
                'Kegiatan: '.$borrowing->activityTypeLabel(),
                'Jadwal: '.$schedule,
                'Peserta: '.$borrowing->participantText(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function vehicleApproverPayload(VehicleBorrowing $borrowing): array
    {
        $borrowing->loadMissing(['campus']);
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $schedule = ($borrowing->starts_at?->timezone($tz)->format('d/m/Y H:i') ?: '—')
            .' – '.($borrowing->ends_at?->timezone($tz)->format('d/m/Y H:i') ?: '—');
        $campus = $borrowing->campus?->name ?: '—';
        $destination = $borrowing->destination ?: '—';

        return [
            'type' => 'vehicle_submitted',
            'kind' => 'kendaraan',
            'title' => 'Peminjaman Kendaraan Baru',
            'message' => $borrowing->borrower_name
                .' mengajukan peminjaman kendaraan menuju '.$destination
                .' dari '.$campus.'. Status: Menunggu Persetujuan. Jadwal: '.$schedule.'.',
            'subject' => '#'.$borrowing->id,
            'subtitle' => $destination,
            'summary' => $schedule,
            'campus' => $campus,
            'url' => route('vehicle-borrowings.show', $borrowing, false),
            'vehicle_borrowing_id' => $borrowing->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function vehiclePayload(VehicleBorrowing $borrowing, string $event): array
    {
        $borrowing->loadMissing(['campus', 'vehicle', 'driver']);
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $schedule = ($borrowing->starts_at?->timezone($tz)->format('d/m/Y H:i') ?: '—')
            .' – '.($borrowing->ends_at?->timezone($tz)->format('d/m/Y H:i') ?: '—');

        $assignment = 'Belum ditetapkan';
        if ($borrowing->vehicle) {
            $assignment = trim($borrowing->vehicle->name.($borrowing->vehicle->plate_number ? ' · '.$borrowing->vehicle->plate_number : ''));
        } elseif ($borrowing->assigned_service) {
            $assignment = 'Layanan '.$borrowing->assigned_service->label();
        }

        $statusLabel = match ($event) {
            'submitted' => 'Menunggu persetujuan Admin — kendaraan belum ditugaskan',
            'approved' => 'Disetujui',
            'cancelled' => 'Persetujuan dibatalkan',
            default => 'Ditolak',
        };

        $lines = [
            $statusLabel,
            'Nomor: '.($borrowing->approval_number ?: '#'.$borrowing->id),
            'Peminjam: '.$borrowing->borrower_name,
            'Tujuan: '.($borrowing->destination ?: '—'),
            'Kampus: '.($borrowing->campus?->name ?: '—'),
            'Jadwal: '.$schedule,
        ];
        if ($borrowing->destinationMapsUrl()) {
            $lines[] = 'Google Maps: '.$borrowing->destinationMapsUrl();
        }
        if ($event === 'approved') {
            $lines[] = 'Penugasan: '.$assignment;
            if ($borrowing->driver?->name) {
                $lines[] = 'Pengemudi: '.$borrowing->driver->name;
            }
        }
        if ($event === 'cancelled' && filled($borrowing->cancellation_reason)) {
            $lines[] = 'Alasan: '.$borrowing->cancellation_reason;
        }

        return [
            'type' => 'vehicle_'.$event,
            'kind' => 'kendaraan',
            'title' => match ($event) {
                'submitted' => 'Pengajuan Peminjaman Kendaraan Baru',
                'approved' => 'Peminjaman Kendaraan Disetujui',
                'cancelled' => 'Persetujuan Kendaraan Dibatalkan',
                default => 'Peminjaman Kendaraan Ditolak',
            },
            'message' => $statusLabel.'. Tujuan: '.($borrowing->destination ?: '—').'.',
            'subject' => $borrowing->approval_number ?: '#'.$borrowing->id,
            'subtitle' => $borrowing->borrower_name,
            'summary' => $schedule,
            'url' => route('vehicle-borrowings.show', $borrowing, false),
            'vehicle_borrowing_id' => $borrowing->id,
            'mail_subject' => '[LOADOUT] Informasi pengajuan peminjaman kendaraan',
            'mail_lines' => $lines,
        ];
    }

    public function notifyDeletionApproval(DeletionApproval $approval, string $event = 'requested'): int
    {
        $approval->loadMissing(['inventoryItem', 'requestedBy']);
        $count = 0;

        $recipients = match ($event) {
            'requested' => $this->usersWithPermission('deletion-approval.approve'),
            default => collect([
                $approval->requestedBy,
                ...$this->usersWithPermission('deletion-approval.approve')->all(),
            ])->filter()->unique('id')->values(),
        };

        foreach ($recipients as $user) {
            if (! $user instanceof User || ! $user->is_active) {
                continue;
            }

            $exists = $user->notifications()
                ->where('type', DeletionApprovalNotification::class)
                ->where('created_at', '>=', now()->subDay())
                ->where('data->deletion_approval_id', $approval->id)
                ->where('data->event', $event)
                ->exists();

            if ($exists) {
                continue;
            }

            $user->notify(new DeletionApprovalNotification($approval, $event));
            $count++;
        }

        return $count;
    }

    public function notifyMaintenanceApproval(Maintenance $maintenance, string $event, ?string $reason = null): int
    {
        $maintenance->loadMissing(['inventoryItem', 'technicianUser', 'creator']);
        $count = 0;

        $recipients = match ($event) {
            'submitted' => $this->usersWithPermission('maintenance.approve'),
            'approved', 'rejected' => collect([
                $maintenance->technicianUser,
                $maintenance->creator,
            ])->filter()->unique('id')->values(),
            default => collect(),
        };

        foreach ($recipients as $user) {
            if (! $user instanceof User || ! $user->is_active) {
                continue;
            }

            $exists = $user->notifications()
                ->where('type', MaintenanceApprovalNotification::class)
                ->where('created_at', '>=', now()->subDay())
                ->where('data->maintenance_id', $maintenance->id)
                ->where('data->event', $event)
                ->exists();

            if ($exists) {
                continue;
            }

            $user->notify(new MaintenanceApprovalNotification($maintenance, $event, $reason));
            $count++;
        }

        return $count;
    }

    public function notifyStockOpnameStatus(StockOpname $stockOpname, string $event): int
    {
        $count = 0;

        foreach ($this->operatorUsers() as $user) {
            $exists = $user->notifications()
                ->where('type', StockOpnameStatusNotification::class)
                ->where('created_at', '>=', now()->subDay())
                ->where('data->stock_opname_id', $stockOpname->id)
                ->where('data->event', $event)
                ->exists();

            if ($exists) {
                continue;
            }

            $user->notify(new StockOpnameStatusNotification($stockOpname, $event));
            $count++;
        }

        return $count;
    }

    public function dispatchWarrantyExpiringAlerts(int $days = 30): int
    {
        $items = InventoryItem::query()
            ->warrantyExpiringSoon($days)
            ->with(['room.floor.building.campus'])
            ->get();

        $users = $this->operatorUsers();
        $count = 0;

        foreach ($items as $item) {
            $daysRemaining = (int) now()->diffInDays($item->warranty_expiry, false);

            foreach ($users as $user) {
                if ($this->notifyWarrantyExpiring($user, $item, max(0, $daysRemaining))) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function dispatchMaintenanceDueAlerts(): int
    {
        $maintenances = Maintenance::query()
            ->whereNotNull('next_schedule')
            ->whereDate('next_schedule', '<=', now()->addDays(7))
            ->whereDate('next_schedule', '>=', now()->subDay())
            ->with(['inventoryItem', 'vendor'])
            ->get();

        $users = $this->operatorUsers();
        $count = 0;

        foreach ($maintenances as $maintenance) {
            foreach ($users as $user) {
                if ($this->notifyMaintenanceDue($user, $maintenance)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Preventive schedules: due within remind_before_days window or overdue.
     */
    public function dispatchMaintenanceScheduleAlerts(): int
    {
        $schedules = app(MaintenanceScheduleService::class)->schedulesNeedingReminder();
        $users = $this->operatorUsers();
        $count = 0;

        foreach ($schedules as $schedule) {
            foreach ($users as $user) {
                if ($this->notifyMaintenanceScheduleReminder($user, $schedule)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function dispatchOverdueBorrowingAlerts(): int
    {
        $borrowings = Borrowing::query()
            ->whereIn('status', [BorrowingStatus::Dipinjam, BorrowingStatus::Disetujui])
            ->whereDate('expected_return_date', '<', now())
            ->with('inventoryItem')
            ->get();

        $users = $this->operatorUsers();
        $count = 0;

        foreach ($borrowings as $borrowing) {
            $borrowing->update(['status' => BorrowingStatus::Terlambat]);

            foreach ($users as $user) {
                if ($this->notifyOverdueBorrowing($user, $borrowing)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @return array{warranty: int, maintenance: int, schedules: int, overdue: int, total: int}
     */
    public function dispatchAllScheduledAlerts(): array
    {
        $warranty = $this->dispatchWarrantyExpiringAlerts();
        $maintenance = $this->dispatchMaintenanceDueAlerts();
        $schedules = $this->dispatchMaintenanceScheduleAlerts();
        $overdue = $this->dispatchOverdueBorrowingAlerts();

        return [
            'warranty' => $warranty,
            'maintenance' => $maintenance,
            'schedules' => $schedules,
            'overdue' => $overdue,
            'total' => $warranty + $maintenance + $schedules + $overdue,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    protected function operatorUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::OPERATOR_ROLES))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    protected function usersWithPermission(string $permission): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->permission($permission)
            ->get();
    }

    protected function alreadyNotified(
        User $user,
        string $notificationClass,
        string $dataKey,
        int|string $dataValue,
        int $withinDays = 1,
    ): bool {
        return $user->notifications()
            ->where('type', $notificationClass)
            ->where('created_at', '>=', now()->subDays($withinDays))
            ->where("data->{$dataKey}", $dataValue)
            ->exists();
    }

    protected function alreadyNotifiedForEvent(
        User $user,
        string $referenceKey,
        int $referenceId,
        string $event,
        int $withinDays = 1,
    ): bool {
        return $user->notifications()
            ->where('type', OperationalBorrowingNotification::class)
            ->where('created_at', '>=', now()->subDays($withinDays))
            ->where("data->{$referenceKey}", $referenceId)
            ->where('data->event', $event)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    protected function vehicleDriverAssignmentPayload(VehicleBorrowing $borrowing): array
    {
        $borrowing->loadMissing(['campus', 'route', 'vehicle', 'driver.user', 'approvedBy']);
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
        $schedule = ($borrowing->starts_at?->timezone($tz)->format('d/m/Y H:i') ?: '—')
            .' – '.($borrowing->ends_at?->timezone($tz)->format('d/m/Y H:i') ?: '—');

        $lines = [
            'Anda ditugaskan sebagai Driver untuk peminjaman kendaraan operasional.',
            'Nomor: '.($borrowing->approval_number ?: '#'.$borrowing->id),
            'Peminjam: '.$borrowing->borrower_name,
            'Telepon peminjam: '.($borrowing->borrower_phone ?: '—'),
            'Unit: '.($borrowing->unit ?: '—'),
            'Kampus: '.($borrowing->campus?->name ?: '—'),
            'Rute: '.($borrowing->origin ?: ($borrowing->route?->name ?: '—')),
            'Tujuan: '.($borrowing->destination ?: '—'),
            'Jadwal: '.$schedule,
            'Jenis: '.($borrowing->assigned_service?->label() ?: ($borrowing->service_type?->label() ?: '—')),
            'Kendaraan: '.($borrowing->vehicle?->label() ?: '—'),
            'Ditetapkan oleh: '.($borrowing->approvedBy?->name ?: '—'),
        ];
        if ($borrowing->destinationMapsUrl()) {
            $lines[] = 'Google Maps: '.$borrowing->destinationMapsUrl();
        }

        return [
            'type' => 'vehicle_driver_assigned',
            'kind' => 'kendaraan',
            'title' => 'Penugasan Pengemudi',
            'message' => 'Anda ditugaskan mengantar '.$borrowing->borrower_name.' ke '.($borrowing->destination ?: 'tujuan').'.',
            'subject' => $borrowing->approval_number ?: '#'.$borrowing->id,
            'subtitle' => $borrowing->borrower_name,
            'summary' => $schedule,
            'url' => route('vehicle-borrowings.show', $borrowing, false),
            'vehicle_borrowing_id' => $borrowing->id,
            'mail_subject' => '[LOADOUT] Penugasan pengemudi peminjaman kendaraan',
            'mail_lines' => $lines,
        ];
    }
}
