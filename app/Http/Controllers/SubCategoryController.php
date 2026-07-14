<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubCategoryRequest;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubCategoryController extends Controller
{
    public function index(Category $category): View
    {
        $subCategories = $category->subCategories()->withCount('inventoryItems')->orderBy('name')->paginate(20);

        return view('master.sub-categories.index', compact('category', 'subCategories'));
    }

    public function store(StoreSubCategoryRequest $request): RedirectResponse
    {
        SubCategory::query()->create([
            ...$request->validated(),
            'slug' => $request->input('slug') ?? Str::slug($request->input('name')),
        ]);

        return back()->with('success', 'Sub kategori berhasil ditambahkan.');
    }

    public function update(StoreSubCategoryRequest $request, SubCategory $subCategory): RedirectResponse
    {
        $subCategory->update($request->validated());

        return back()->with('success', 'Sub kategori berhasil diperbarui.');
    }

    public function destroy(SubCategory $subCategory): RedirectResponse
    {
        $subCategory->delete();

        return back()->with('success', 'Sub kategori berhasil dihapus.');
    }
}
