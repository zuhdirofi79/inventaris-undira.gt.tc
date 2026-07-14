<?php

namespace App\Http\Controllers;

use App\Enums\StockOpnameStatus;
use App\Models\Campus;
use App\Models\InventoryItem;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockOpnameController extends Controller
{
    public function index(): View
    {
        $stockOpnames = StockOpname::query()
            ->with(['campus', 'creator'])
            ->withCount('items')
            ->latest()
            ->paginate(20);

        return view('operations.stock-opnames.index', compact('stockOpnames'));
    }

    public function create(): View
    {
        return view('operations.stock-opnames.create', [
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'campus_id' => ['required', 'exists:campuses,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $stockOpname = StockOpname::query()->create([
            ...$data,
            'status' => StockOpnameStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('stock-opnames.show', $stockOpname)->with('success', 'Stock opname berhasil dibuat.');
    }

    public function show(StockOpname $stockOpname): View
    {
        $stockOpname->load(['campus', 'creator', 'items.inventoryItem.category']);

        $stats = [
            'total_scanned' => $stockOpname->items()->whereNotNull('scanned_at')->count(),
            'total_items' => $stockOpname->items()->count(),
        ];

        return view('operations.stock-opnames.show', compact('stockOpname', 'stats'));
    }

    public function start(StockOpname $stockOpname): RedirectResponse
    {
        $stockOpname->update([
            'status' => StockOpnameStatus::InProgress,
            'started_at' => now(),
        ]);

        return back()->with('success', 'Stock opname dimulai.');
    }

    public function complete(StockOpname $stockOpname): RedirectResponse
    {
        $stockOpname->update([
            'status' => StockOpnameStatus::Completed,
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Stock opname selesai.');
    }

    public function scan(Request $request, StockOpname $stockOpname): JsonResponse
    {
        $request->validate(['inventory_number' => ['required', 'string']]);

        $item = InventoryItem::query()
            ->where('inventory_number', $request->input('inventory_number'))
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Inventaris tidak ditemukan.'], 404);
        }

        $opnameItem = StockOpnameItem::query()->firstOrCreate(
            [
                'stock_opname_id' => $stockOpname->id,
                'inventory_item_id' => $item->id,
            ],
            ['status' => 'found']
        );

        $opnameItem->update(['scanned_at' => now()]);

        return response()->json([
            'message' => 'Berhasil discan.',
            'item' => $item->load('category'),
        ]);
    }

    public function destroy(StockOpname $stockOpname): RedirectResponse
    {
        $stockOpname->delete();

        return redirect()->route('stock-opnames.index')->with('success', 'Stock opname berhasil dihapus.');
    }
}
