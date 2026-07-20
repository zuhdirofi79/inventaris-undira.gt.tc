<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'klasifikasi');
        if (! in_array($tab, ['klasifikasi', 'units', 'pengadaan', 'lokasi'], true)) {
            $tab = 'klasifikasi';
        }

        $categories = Category::query()
            ->withCount('subCategories', 'inventoryItems')
            ->orderBy('name')
            ->paginate(20, ['*'], 'categories_page')
            ->withQueryString();

        $orgUnits = OrgUnit::query()
            ->withCount('inventoryItems')
            ->orderBy('name')
            ->paginate(20, ['*'], 'units_page')
            ->withQueryString();

        $procurementTypes = ProcurementType::query()
            ->withCount('inventoryItems')
            ->orderBy('name')
            ->paginate(20, ['*'], 'procurement_page')
            ->withQueryString();

        return view('master.categories.index', compact(
            'tab',
            'categories',
            'orgUnits',
            'procurementTypes',
        ));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create([
            ...$request->validated(),
            'slug' => $request->input('slug') ?? Str::slug($request->input('name')),
        ]);

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', 'Klasifikasi berhasil ditambahkan.');
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', 'Klasifikasi berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->subCategories()->exists()) {
            return redirect()
                ->route('categories.index', ['tab' => 'klasifikasi'])
                ->with('error', 'Kategori tidak dapat dihapus karena masih memiliki sub kategori.');
        }

        if ($category->inventoryItems()->exists()) {
            return redirect()
                ->route('categories.index', ['tab' => 'klasifikasi'])
                ->with('error', 'Kategori tidak dapat dihapus karena masih digunakan inventaris.');
        }

        $category->delete();

        return redirect()
            ->route('categories.index', ['tab' => 'klasifikasi'])
            ->with('success', 'Klasifikasi berhasil dihapus.');
    }
}
