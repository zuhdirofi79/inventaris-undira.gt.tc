<?php

namespace App\Http\Controllers;

use App\Enums\BorrowingStatus;
use App\Enums\InventoryStatus;
use App\Http\Requests\StoreBorrowingRequest;
use App\Http\Requests\UpdateBorrowingRequest;
use App\Models\Borrowing;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BorrowingController extends Controller
{
    public function index(): View
    {
        $borrowings = Borrowing::query()
            ->with(['inventoryItem.category', 'inventoryItem.room', 'approvedBy'])
            ->latest()
            ->paginate(20);

        return view('operations.borrowings.index', compact('borrowings'));
    }

    public function create(): View
    {
        $items = InventoryItem::query()
            ->where('status', InventoryStatus::Aktif)
            ->orderBy('name')
            ->get(['id', 'inventory_number', 'name']);

        return view('operations.borrowings.create', compact('items'));
    }

    public function store(StoreBorrowingRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $borrowing = Borrowing::query()->create([
                ...$request->validated(),
                'status' => $request->input('status', BorrowingStatus::Dipinjam),
                'approved_by' => $request->user()->id,
            ]);

            InventoryItem::query()
                ->where('id', $borrowing->inventory_item_id)
                ->update(['status' => InventoryStatus::Dipinjam]);
        });

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
        DB::transaction(function () use ($request, $borrowing) {
            $borrowing->update($request->validated());

            if ($borrowing->status === BorrowingStatus::Dikembalikan) {
                $borrowing->update(['return_date' => $borrowing->return_date ?? now()]);
                InventoryItem::query()
                    ->where('id', $borrowing->inventory_item_id)
                    ->update(['status' => InventoryStatus::Aktif]);
            }
        });

        return redirect()->route('borrowings.show', $borrowing)->with('success', 'Peminjaman berhasil diperbarui.');
    }

    public function destroy(Borrowing $borrowing): RedirectResponse
    {
        $borrowing->delete();

        return back()->with('success', 'Data peminjaman berhasil dihapus.');
    }
}
