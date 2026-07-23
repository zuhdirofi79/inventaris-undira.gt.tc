<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreSubCategoryRequest;
use App\Models\Category;
use App\Models\SubCategory;
use App\Services\AuditLogService;
use App\Services\TrashCascadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubCategoryController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
        protected AuditLogService $audit,
    ) {}

    public function index(Category $category): View
    {
        $subCategories = $category->subCategories()->withCount('inventoryItems')->orderBy('name')->paginate(20);

        return view('master.sub-categories.index', compact('category', 'subCategories'));
    }

    public function store(StoreSubCategoryRequest $request): RedirectResponse
    {
        $subCategory = SubCategory::query()->create([
            ...$request->validated(),
            'slug' => $request->input('slug') ?? Str::slug($request->input('name')),
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $subCategory,
            null,
            $subCategory->only(['category_id', 'code', 'name', 'slug', 'is_active']),
            'SUB_CATEGORY_CREATED',
        );

        return back()->with('success', 'Sub kategori berhasil ditambahkan.');
    }

    public function update(StoreSubCategoryRequest $request, SubCategory $subCategory): RedirectResponse
    {
        $old = $subCategory->only(['category_id', 'code', 'name', 'slug', 'is_active']);
        $subCategory->update($request->validated());

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $subCategory,
            $old,
            $subCategory->only(['category_id', 'code', 'name', 'slug', 'is_active']),
            'SUB_CATEGORY_UPDATED',
        );

        return back()->with('success', 'Sub kategori berhasil diperbarui.');
    }

    public function destroy(Request $request, SubCategory $subCategory): RedirectResponse
    {
        $this->trash->softDelete($subCategory, $request->user());

        return back()->with('success', 'Sub kategori dipindahkan ke Trash.');
    }
}
