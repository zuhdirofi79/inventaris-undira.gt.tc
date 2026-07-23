<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\BorrowingStatus;
use App\Enums\InventoryStatus;
use App\Http\Requests\StoreBorrowingRequest;
use App\Http\Requests\UpdateBorrowingRequest;
use App\Models\Borrowing;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BorrowingController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
        protected NotificationService $notifications,
    ) {}

    public function index(): View
    {
        $borrowings = Borrowing::query()
            ->with(['inventoryItem.category', 'inventoryItem.room', 'approvedBy'])
            ->latest()
            ->paginate(20);

        return view('operations.borrowings.index', compact('borrowings'));
    }

    public function create(Request $request): View
    {
        $selectedItem = null;
        $selectedId = $request->integer('inventory_item_id') ?: (int) old('inventory_item_id');
        if ($selectedId > 0) {
            $selectedItem = InventoryItem::query()
                ->where('status', InventoryStatus::Aktif)
                ->find($selectedId, ['id', 'inventory_number', 'name']);
        }

        return view('operations.borrowings.create', [
            'selectedItem' => $selectedItem,
            'lookupStatus' => InventoryStatus::Aktif->value,
        ]);
    }

    public function store(StoreBorrowingRequest $request): RedirectResponse
    {
        $borrowing = DB::transaction(function () use ($request) {
            $borrowing = Borrowing::query()->create([
                ...$request->validated(),
                'status' => $request->input('status', BorrowingStatus::Dipinjam),
                'approved_by' => $request->user()->id,
            ]);

            InventoryItem::query()
                ->where('id', $borrowing->inventory_item_id)
                ->update(['status' => InventoryStatus::Dipinjam]);

            return $borrowing;
        });

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $borrowing,
            null,
            $borrowing->only(['inventory_item_id', 'borrower_name', 'borrow_date', 'expected_return_date', 'status']),
            'BORROWING_CREATED',
        );

        $this->notifications->notifyBorrowingCreated($borrowing->load('inventoryItem'));

        return redirect()->route('borrowings.index')->with('success', 'Peminjaman berhasil dicatat.');
    }

    public function show(Borrowing $borrowing): View
    {
        $borrowing->load(['inventoryItem.room.floor.building.campus', 'approvedBy']);

        return view('operations.borrowings.show', compact('borrowing'));
    }

    public function edit(Borrowing $borrowing): View
    {
        return view('operations.borrowings.edit', compact('borrowing'));
    }

    public function update(UpdateBorrowingRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $old = $borrowing->only(['status', 'return_date', 'expected_return_date', 'borrower_name']);

        DB::transaction(function () use ($request, $borrowing) {
            $borrowing->update($request->validated());

            if ($borrowing->status === BorrowingStatus::Dikembalikan) {
                $borrowing->update(['return_date' => $borrowing->return_date ?? now()]);
                InventoryItem::query()
                    ->where('id', $borrowing->inventory_item_id)
                    ->update(['status' => InventoryStatus::Aktif]);
            }
        });

        $borrowing->refresh();

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            $old,
            $borrowing->only(['status', 'return_date', 'expected_return_date', 'borrower_name']),
            'BORROWING_UPDATED',
        );

        return redirect()->route('borrowings.show', $borrowing)->with('success', 'Peminjaman berhasil diperbarui.');
    }

    public function destroy(Borrowing $borrowing): RedirectResponse
    {
        $snapshot = $borrowing->only(['inventory_item_id', 'borrower_name', 'status']);

        DB::transaction(function () use ($borrowing) {
            if (in_array($borrowing->status, [BorrowingStatus::Dipinjam, BorrowingStatus::Terlambat], true)) {
                InventoryItem::query()
                    ->where('id', $borrowing->inventory_item_id)
                    ->update(['status' => InventoryStatus::Aktif]);
            }

            $borrowing->delete();
        });

        $this->audit->log(
            request()->user(),
            AuditAction::Delete,
            null,
            $snapshot,
            null,
            'BORROWING_DELETED',
        );

        return back()->with('success', 'Data peminjaman berhasil dihapus.');
    }
}
