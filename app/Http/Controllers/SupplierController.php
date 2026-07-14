<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::query()->withCount('inventoryItems')->orderBy('name')->paginate(20);

        return view('master.suppliers.index', compact('suppliers'));
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create($request->validated());

        return back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return back()->with('success', 'Supplier berhasil dihapus.');
    }
}
