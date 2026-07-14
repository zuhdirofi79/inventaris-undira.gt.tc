<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->withCount('subCategories', 'inventoryItems')->orderBy('name')->paginate(20);

        return view('master.categories.index', compact('categories'));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create([
            ...$request->validated(),
            'slug' => $request->input('slug') ?? Str::slug($request->input('name')),
        ]);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus.');
    }
}
