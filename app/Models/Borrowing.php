<?php

namespace App\Models;

use App\Casts\PlannedDateTimeCast;
use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Borrowing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'inventory_item_id',
        'borrower_name',
        'borrower_email',
        'borrower_phone',
        'borrow_date',
        'return_date',
        'expected_return_date',
        'status',
        'notes',
        'approved_by',
        'requested_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'approval_notes',
        'approval_number',
        'notification_sent',
        'supporting_document_path',
        'supporting_document_original_name',
        'supporting_document_size',
    ];

    protected function casts(): array
    {
        return [
            'borrow_date' => PlannedDateTimeCast::class,
            'return_date' => 'date',
            'expected_return_date' => PlannedDateTimeCast::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'notification_sent' => 'boolean',
            'supporting_document_size' => 'integer',
            'status' => BorrowingStatus::class,
        ];
    }

    public function hasPlannedTime(string $attribute): bool
    {
        $raw = $this->getRawOriginal($attribute);
        if (! is_string($raw) || $raw === '') {
            $raw = $this->getAttributes()[$attribute] ?? null;
        }

        return is_string($raw) && preg_match('/\d{2}:\d{2}/', $raw) === 1;
    }

    public function plannedInputValue(string $attribute): string
    {
        $value = $this->getAttribute($attribute);
        if (! $value || ! $this->hasPlannedTime($attribute)) {
            return '';
        }

        return $value->timezone((string) config('app.timezone'))->format('Y-m-d\TH:i');
    }

    public function plannedScheduleText(string $attribute): string
    {
        $value = $this->getAttribute($attribute);
        if (! $value) {
            return '—';
        }

        $value = $value->timezone((string) config('app.timezone'));
        if (! $this->hasPlannedTime($attribute)) {
            return $value->translatedFormat('d F Y');
        }

        return $value->translatedFormat('d F Y, H:i').' WIB';
    }

    public function plannedScheduleDate(string $attribute): string
    {
        $value = $this->getAttribute($attribute);

        return $value
            ? $value->timezone((string) config('app.timezone'))->format('d/m/Y')
            : '—';
    }

    public function plannedScheduleTime(string $attribute): ?string
    {
        $value = $this->getAttribute($attribute);
        if (! $value || ! $this->hasPlannedTime($attribute)) {
            return null;
        }

        return $value->timezone((string) config('app.timezone'))->format('H:i');
    }

    public function hasSupportingDocument(): bool
    {
        return filled($this->supporting_document_path);
    }

    /**
     * Legacy single-item link (first / primary item). Kept for backward compatibility.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BorrowingItem::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * All inventory lines for this transaction (eager-safe).
     *
     * @return Collection<int, BorrowingItem>
     */
    public function lineItems(): Collection
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return $this->items;
        }

        if ($this->items()->exists()) {
            return $this->items()->with('inventoryItem')->get();
        }

        // Legacy fallback before backfill / edge cases.
        if ($this->inventory_item_id) {
            $this->loadMissing('inventoryItem');

            return collect([
                new BorrowingItem([
                    'borrowing_id' => $this->id,
                    'inventory_item_id' => $this->inventory_item_id,
                    'qty' => 1,
                    'status' => match ($this->status) {
                        BorrowingStatus::Dikembalikan => BorrowingItemStatus::Dikembalikan,
                        BorrowingStatus::Ditolak => BorrowingItemStatus::Ditolak,
                        BorrowingStatus::Disetujui => BorrowingItemStatus::Disetujui,
                        BorrowingStatus::Dipinjam,
                        BorrowingStatus::Terlambat => BorrowingItemStatus::Dipinjam,
                        default => BorrowingItemStatus::MenungguPersetujuan,
                    },
                ]),
            ]);
        }

        return collect();
    }

    public function itemsCount(): int
    {
        if ($this->relationLoaded('items')) {
            return $this->items->count();
        }

        $count = $this->items()->count();

        return $count > 0 ? $count : ($this->inventory_item_id ? 1 : 0);
    }

    /**
     * Lama pinjam aktual.
     * Awal: borrowed_at paling awal pada item yang diserahkan.
     * Akhir: returned_at paling akhir. Jika pengembalian hanya tercatat sebagai tanggal, selisihnya hari kalender.
     * Peminjaman yang belum selesai ditampilkan sebagai durasi berjalan.
     */
    public function loanDurationLabel(?CarbonInterface $asOf = null): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $start = $items
            ->map(fn (BorrowingItem $item) => $item->borrowed_at)
            ->filter()
            ->sortBy(fn (CarbonInterface $moment) => $moment->getTimestamp())
            ->first();

        $closed = $this->status === BorrowingStatus::Dikembalikan
            || ($items->isNotEmpty() && $this->allItemsReturned());

        if (! $closed) {
            if (! $start) {
                return '—';
            }

            $asOf ??= now();
            if ($asOf->lessThan($start)) {
                return '—';
            }

            return 'Berjalan: '.$this->formatElapsed($start, $asOf);
        }

        $end = $items
            ->map(fn (BorrowingItem $item) => $item->returned_at)
            ->filter()
            ->sortBy(fn (CarbonInterface $moment) => $moment->getTimestamp())
            ->last();

        if ($start && $end) {
            return $this->formatElapsed($start, $end);
        }

        if ($start && $this->return_date) {
            $zone = (string) config('app.timezone');
            $startDay = $start->copy()->timezone($zone)->startOfDay();
            $returnDay = $this->return_date->copy()->timezone($zone)->startOfDay();
            if ($returnDay->lt($startDay)) {
                return '—';
            }
            if ($returnDay->equalTo($startDay)) {
                return 'Kurang dari 1 hari';
            }

            return $startDay->diff($returnDay)->days.' hari';
        }

        return '—';
    }

    protected function formatElapsed(CarbonInterface $start, CarbonInterface $end): string
    {
        $interval = $start->diff($end);
        if ($interval->invert === 1) {
            return '—';
        }

        $days = (int) $interval->days;
        $hours = (int) $interval->h;
        $minutes = (int) $interval->i;

        if ($days > 0) {
            return $hours > 0 ? $days.' hari '.$hours.' jam' : $days.' hari';
        }
        if ($hours > 0) {
            return $minutes > 0 ? $hours.' jam '.$minutes.' menit' : $hours.' jam';
        }

        return $minutes.' menit';
    }

    public function allItemsReturned(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->isEmpty()) {
            return $this->status === BorrowingStatus::Dikembalikan;
        }

        // Rejected lines are settled without physical return.
        return $items->every(
            fn (BorrowingItem $item) => $item->status === BorrowingItemStatus::Dikembalikan
                || $item->status === BorrowingItemStatus::Ditolak
        ) && $items->contains(
            fn (BorrowingItem $item) => $item->status === BorrowingItemStatus::Dikembalikan
        );
    }

    public function hasPendingItemApprovals(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->contains(
            fn (BorrowingItem $item) => $item->status === BorrowingItemStatus::MenungguPersetujuan
        );
    }

    /**
     * @return array{approved: int, rejected: int, pending: int, borrowed: int, returned: int}
     */
    public function itemDecisionCounts(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return [
            'pending' => $items->where('status', BorrowingItemStatus::MenungguPersetujuan)->count(),
            'approved' => $items->whereIn('status', [
                BorrowingItemStatus::Disetujui,
                BorrowingItemStatus::Dipinjam,
                BorrowingItemStatus::Dikembalikan,
            ])->count(),
            'rejected' => $items->where('status', BorrowingItemStatus::Ditolak)->count(),
            'borrowed' => $items->where('status', BorrowingItemStatus::Dipinjam)->count(),
            'returned' => $items->where('status', BorrowingItemStatus::Dikembalikan)->count(),
        ];
    }

    public function approvalStatusLabel(): string
    {
        if ($this->status === BorrowingStatus::MenungguPersetujuan) {
            return 'Menunggu Persetujuan';
        }

        if ($this->status === BorrowingStatus::Ditolak || $this->rejected_at) {
            return 'Ditolak';
        }

        if ($this->approved_at || in_array($this->status, [
            BorrowingStatus::Disetujui,
            BorrowingStatus::Dipinjam,
            BorrowingStatus::Terlambat,
            BorrowingStatus::Dikembalikan,
        ], true)) {
            return 'Disetujui';
        }

        return $this->status?->label() ?? '-';
    }

    /**
     * Unique campus names for this transaction (eager-load safe).
     * Path: BorrowingItem → InventoryItem → Room → Floor → Building → Campus
     * (legacy header inventoryItem used when no line items exist).
     *
     * @return Collection<int, string>
     */
    public function campusNames(): Collection
    {
        $inventories = collect();

        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            $inventories = $this->items
                ->map(fn (BorrowingItem $line) => $line->relationLoaded('inventoryItem')
                    ? $line->inventoryItem
                    : $line->inventoryItem()->with('room.floor.building.campus')->first())
                ->filter();
        } elseif ($this->items()->exists()) {
            $inventories = $this->items()
                ->with('inventoryItem.room.floor.building.campus')
                ->get()
                ->pluck('inventoryItem')
                ->filter();
        } elseif ($this->inventory_item_id) {
            $this->loadMissing('inventoryItem.room.floor.building.campus');
            if ($this->inventoryItem) {
                $inventories = collect([$this->inventoryItem]);
            }
        }

        return $inventories
            ->map(fn ($item) => $item?->room?->floor?->building?->campus?->name)
            ->filter(fn ($name) => filled($name))
            ->unique()
            ->values();
    }

    /**
     * Compact campus label for list/detail UI.
     */
    public function campusSummary(): string
    {
        $names = $this->campusNames();

        if ($names->isEmpty()) {
            return '—';
        }

        if ($names->count() > 1) {
            return 'Multi Kampus';
        }

        return (string) $names->first();
    }

    public function isMultiCampus(): bool
    {
        return $this->campusNames()->count() > 1;
    }

    /**
     * Whether an inventory unit is blocked by an open borrowing (header or line).
     */
    public static function inventoryIsBlocked(int $inventoryItemId, ?int $exceptBorrowingId = null): bool
    {
        if ($inventoryItemId < 1) {
            return false;
        }

        $viaLines = BorrowingItem::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->whereIn('status', BorrowingItemStatus::blockingValues())
            ->whereHas('borrowing', function ($q) use ($exceptBorrowingId) {
                $q->whereIn('status', BorrowingStatus::blockingValues());
                if ($exceptBorrowingId) {
                    $q->where('id', '!=', $exceptBorrowingId);
                }
            })
            ->exists();

        if ($viaLines) {
            return true;
        }

        // Legacy header-only rows (pre-items or missing line).
        return static::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->whereIn('status', BorrowingStatus::blockingValues())
            ->when($exceptBorrowingId, fn ($q) => $q->where('id', '!=', $exceptBorrowingId))
            ->whereDoesntHave('items')
            ->exists();
    }
}
