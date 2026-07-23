<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        if ($category instanceof Category) {
            return $this->user()?->can('update', $category) ?? false;
        }

        return $this->user()?->can('create', Category::class) ?? false;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('categories', 'code')->ignore($categoryId)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($categoryId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Kode kategori',
            'name' => 'Nama kategori',
            'slug' => 'Slug kategori',
            'description' => 'Deskripsi',
            'is_active' => 'Status',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode kategori wajib diisi.',
            'code.unique' => 'Kode kategori sudah digunakan.',
            'name.required' => 'Nama kategori wajib diisi.',
            'slug.unique' => 'Slug kategori sudah digunakan.',
            'is_active.required' => 'Status kategori wajib dipilih.',
        ];
    }
}
