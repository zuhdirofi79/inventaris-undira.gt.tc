<?php

namespace App\Models;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
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
    ];

    protected function casts(): array
    {
        return [
            'borrow_date' => 'date',
            'return_date' => 'date',
            'expected_return_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'notification_sent' => 'boolean',
            'status' => BorrowingStatus::class,
        ];
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
                        BorrowingStatus::Disetujui,
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

    public function allItemsReturned(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->isEmpty()) {
            return $this->status === BorrowingStatus::Dikembalikan;
        }

        return $items->every(
            fn (BorrowingItem $item) => $item->status === BorrowingItemStatus::Dikembalikan
        );
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
