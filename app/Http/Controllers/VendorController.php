<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorRequest;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(): View
    {
        $vendors = Vendor::query()->withCount('maintenances')->orderBy('name')->paginate(20);

        return view('master.vendors.index', compact('vendors'));
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        Vendor::query()->create($request->validated());

        return back()->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function update(StoreVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($request->validated());

        return back()->with('success', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $vendor->delete();

        return back()->with('success', 'Vendor berhasil dihapus.');
    }
}
