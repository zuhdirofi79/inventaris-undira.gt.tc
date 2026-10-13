<?php

namespace App\Services;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Schedule-aware availability for individual inventory borrowing.
 * Operational status is not rewritten here.
 */
class InventoryAvailability
{
    /**
     * @return array{start: ?CarbonInterface, end: ?CarbonInterface}
     */
    public function window(mixed $start, mixed $end): array
    {
        return [
            'start' => $this->parse($start),
            'end' => $this->parse($end),
        ];
    }

    /**
     * @return array{start: CarbonInterface, end: CarbonInterface}|null
     */
    public function completeWindow(mixed $start, mixed $end): ?array
    {
        $window = $this->window($start, $end);
        if (! $window['start'] instanceof CarbonInterface || ! $window['end'] instanceof CarbonInterface) {
            return null;
        }

        if (! $window['end']->greaterThan($window['start'])) {
            return null;
        }

        return [
            'start' => $window['start'],
            'end' => $window['end'],
        ];
    }

    /**
     * @param  list<int>  $inventoryIds
     * @return array<int, array{state: string, source: ?string, message: ?string, reason: ?string}>
     */
    public function classify(array $inventoryIds, ?CarbonInterface $start, ?CarbonInterface $end, ?int $exceptBorrowingId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $inventoryIds))));
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = [
                'state' => 'available',
                'source' => null,
                'message' => null,
                'reason' => null,
            ];
        }

        if ($ids === []) {
            return $result;
        }

        $items = InventoryItem::query()
            ->whereIn('id', $ids)
            ->get(['id', 'inventory_number', 'room_id', 'status'])
            ->keyBy('id');

        $scheduled = $start instanceof CarbonInterface
            && $end instanceof CarbonInterface
            && $end->greaterThan($start);

        $lines = DB::table('borrowing_items')
            ->join('borrowings', 'borrowings.id', '=', 'borrowing_items.borrowing_id')
            ->whereNull('borrowings.deleted_at')
            ->whereIn('borrowing_items.inventory_item_id', $ids)
            ->whereIn('borrowing_items.status', [
                BorrowingItemStatus::MenungguPersetujuan->value,
                BorrowingItemStatus::Disetujui->value,
                BorrowingItemStatus::Dipinjam->value,
            ])
            ->whereIn('borrowings.status', BorrowingStatus::blockingValues())
            ->when($exceptBorrowingId, fn ($query) => $query->where('borrowings.id', '!=', $exceptBorrowingId))
            ->get([
                'borrowing_items.inventory_item_id',
                'borrowing_items.status as item_status',
                'borrowings.borrow_date',
                'borrowings.expected_return_date',
            ]);

        foreach ($lines as $line) {
            $id = (int) $line->inventory_item_id;
            $decision = $this->loanDecision(
                (string) $line->item_status,
                $this->parse($line->borrow_date),
                $this->parse($line->expected_return_date),
                $scheduled ? $start : null,
                $scheduled ? $end : null,
            );
            if ($decision !== null) {
                $this->prefer($result, $id, $decision, (string) ($items->get($id)?->inventory_number ?? $id));
            }
        }

        $legacy = DB::table('borrowings')
            ->whereNull('deleted_at')
            ->whereIn('inventory_item_id', $ids)
            ->whereIn('status', BorrowingStatus::blockingValues())
            ->when($exceptBorrowingId, fn ($query) => $query->where('id', '!=', $exceptBorrowingId))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('borrowing_items')
                    ->whereColumn('borrowing_items.borrowing_id', 'borrowings.id');
            })
            ->get(['inventory_item_id', 'status', 'borrow_date', 'expected_return_date']);

        foreach ($legacy as $row) {
            $id = (int) $row->inventory_item_id;
            $itemStatus = match ((string) $row->status) {
                BorrowingStatus::Dipinjam->value, BorrowingStatus::Terlambat->value => BorrowingItemStatus::Dipinjam->value,
                BorrowingStatus::Disetujui->value => BorrowingItemStatus::Disetujui->value,
                BorrowingStatus::MenungguPersetujuan->value => BorrowingItemStatus::MenungguPersetujuan->value,
                default => null,
            };
            if ($itemStatus === null) {
                continue;
            }
            $decision = $this->loanDecision(
                $itemStatus,
                $this->parse($row->borrow_date),
                $this->parse($row->expected_return_date),
                $scheduled ? $start : null,
                $scheduled ? $end : null,
            );
            if ($decision !== null) {
                $this->prefer($result, $id, $decision, (string) ($items->get($id)?->inventory_number ?? $id));
            }
        }

        if ($scheduled) {
            $this->applyRoomConflicts($result, $items, $start, $end);
        }

        return $result;
    }

    /**
     * @param  list<int>  $inventoryIds
     */
    public function assertSelectable(
        array $inventoryIds,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $exceptBorrowingId = null,
        string $errorKey = 'items',
    ): void {
        $ids = array_values(array_unique(array_filter(array_map('intval', $inventoryIds))));
        $items = InventoryItem::query()->whereIn('id', $ids)->get()->keyBy('id');
        $states = $this->classify($ids, $start, $end, $exceptBorrowingId);
        $messages = [];

        foreach ($ids as $id) {
            $item = $items->get($id);
            if (! $item) {
                $messages[] = 'Inventaris tidak ditemukan.';

                continue;
            }

            $status = $item->status instanceof InventoryStatus
                ? $item->status
                : InventoryStatus::tryFrom((string) $item->status);

            if (! $status?->isBorrowable()) {
                $messages[] = sprintf(
                    'Inventaris %s tidak dapat dipinjam (status: %s). Hanya status Aktif yang boleh dipinjam.',
                    $item->inventory_number,
                    $status?->label() ?? 'tidak valid'
                );

                continue;
            }

            $state = $states[$id]['state'] ?? 'blocked';
            if ($state !== 'available') {
                $messages[] = (string) ($states[$id]['message'] ?? "Inventaris {$item->inventory_number} tidak tersedia pada jadwal tersebut.");
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages([
                $errorKey => implode(' ', $messages),
            ]);
        }
    }

    public function assertRoomFreeOfCommittedInventory(int $roomId, CarbonInterface $start, CarbonInterface $end, string $errorKey): void
    {
        if ($roomId < 1) {
            return;
        }

        $itemIds = InventoryItem::query()
            ->where('room_id', $roomId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($itemIds === []) {
            return;
        }

        $physicallyBorrowed = InventoryItem::query()
            ->whereIn('id', $itemIds)
            ->where('status', InventoryStatus::Dipinjam->value)
            ->exists();

        if ($physicallyBorrowed) {
            throw ValidationException::withMessages([
                $errorKey => 'Ruangan tidak dapat digunakan karena ada inventaris di dalamnya yang sedang dipinjam dan belum dikembalikan.',
            ]);
        }

        $states = $this->classify($itemIds, $start, $end);
        foreach ($states as $state) {
            if (in_array($state['source'], ['loan_active', 'loan_approved'], true)) {
                throw ValidationException::withMessages([
                    $errorKey => 'Ruangan tidak dapat digunakan karena ada inventaris di dalamnya yang sudah disetujui atau sedang dipinjam pada jadwal tersebut.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, array{state: string, source: ?string, message: ?string, reason: ?string}>  $result
     * @param  \Illuminate\Support\Collection<int, InventoryItem>  $items
     */
    private function applyRoomConflicts(array &$result, $items, CarbonInterface $start, CarbonInterface $end): void
    {
        $roomIds = $items->pluck('room_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($roomIds === []) {
            return;
        }

        $rooms = DB::table('room_borrowings')
            ->whereIn('room_id', $roomIds)
            ->where('status', FacilityRequestStatus::Disetujui->value)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->get(['room_id', 'status']);

        $byRoom = [];
        foreach ($rooms as $room) {
            $byRoom[(int) $room->room_id] = 'room_approved';
        }

        foreach ($items as $item) {
            $source = $byRoom[(int) $item->room_id] ?? null;
            if ($source === null) {
                continue;
            }
            $this->prefer($result, (int) $item->id, $source, (string) $item->inventory_number);
        }
    }

    private function loanDecision(
        string $itemStatus,
        ?CarbonInterface $existingStart,
        ?CarbonInterface $existingEnd,
        ?CarbonInterface $start,
        ?CarbonInterface $end,
    ): ?string {
        if ($itemStatus === BorrowingItemStatus::Dipinjam->value) {
            return 'loan_active';
        }

        if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            return null;
        }

        if (! $this->overlaps($existingStart, $existingEnd, $start, $end)) {
            return null;
        }

        return match ($itemStatus) {
            BorrowingItemStatus::Disetujui->value => 'loan_approved',
            BorrowingItemStatus::MenungguPersetujuan->value => 'loan_pending',
            default => null,
        };
    }

    private function overlaps(?CarbonInterface $existingStart, ?CarbonInterface $existingEnd, CarbonInterface $start, CarbonInterface $end): bool
    {
        if (! $existingStart instanceof CarbonInterface) {
            return true;
        }

        if (! $existingEnd instanceof CarbonInterface) {
            return $existingStart->lt($end);
        }

        return $existingStart->lt($end) && $existingEnd->gt($start);
    }

    /**
     * @param  array<int, array{state: string, source: ?string, message: ?string, reason: ?string}>  $result
     */
    private function prefer(array &$result, int $id, string $source, string $number): void
    {
        if (! isset($result[$id])) {
            return;
        }

        $state = match ($source) {
            'loan_pending', 'room_pending' => 'pending',
            default => 'blocked',
        };
        $rank = ['available' => 0, 'pending' => 1, 'blocked' => 2];
        $sourceRank = [
            'loan_active' => 5,
            'loan_approved' => 4,
            'room_approved' => 3,
            'loan_pending' => 2,
            'room_pending' => 1,
        ];
        $current = $result[$id];
        $currentSourceRank = $sourceRank[$current['source'] ?? ''] ?? 0;
        if ($rank[$state] < $rank[$current['state']] || ($state === $current['state'] && ($sourceRank[$source] ?? 0) <= $currentSourceRank)) {
            return;
        }

        $copy = $this->copy($number, $source);
        $result[$id] = [
            'state' => $state,
            'source' => $source,
            'message' => $copy['message'],
            'reason' => $copy['reason'],
        ];
    }

    /**
     * @return array{message: string, reason: string}
     */
    private function copy(string $number, string $source): array
    {
        return match ($source) {
            'loan_active' => [
                'message' => "Inventaris {$number} sedang dipinjam dan belum dikembalikan.",
                'reason' => 'Sedang dipinjam dan belum dikembalikan.',
            ],
            'loan_approved' => [
                'message' => "Inventaris {$number} sudah disetujui untuk jadwal yang berbenturan.",
                'reason' => 'Sudah disetujui untuk jadwal yang berbenturan.',
            ],
            'room_approved' => [
                'message' => "Inventaris {$number} tidak tersedia karena ruangan sedang digunakan pada jadwal tersebut.",
                'reason' => 'Ruangan sedang digunakan pada jadwal tersebut.',
            ],
            'loan_pending' => [
                'message' => "Inventaris {$number} sedang diajukan oleh pengguna lain.",
                'reason' => 'Sedang diajukan oleh pengguna lain.',
            ],
            default => [
                'message' => "Inventaris {$number} tidak dapat dipilih karena ruangan sedang diajukan oleh pengguna lain.",
                'reason' => 'Ruangan sedang diajukan oleh pengguna lain.',
            ],
        };
    }

    private function parse(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::parse($value)->timezone((string) config('app.timezone'));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $value, (string) config('app.timezone'));
            } catch (\Throwable) {
                $parsed = false;
            }
            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        try {
            return CarbonImmutable::parse($value, (string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
