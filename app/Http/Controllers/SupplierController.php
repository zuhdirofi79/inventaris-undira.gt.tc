<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use App\Services\TrashCascadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
    ) {}

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

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->trash->softDelete($supplier, $request->user());

        return back()->with('success', 'Supplier dipindahkan ke Trash.');
    }
}
