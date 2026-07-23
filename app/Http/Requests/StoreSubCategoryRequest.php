<?php

namespace App\Http\Requests;

use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('category.manage') ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('subCategory') !== null;

        return [
            'category_id' => [$isUpdate ? 'sometimes' : 'required', ActiveExists::rule('categories')],
            'code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'Kategori',
            'code' => 'Kode sub kategori',
            'name' => 'Nama sub kategori',
            'slug' => 'Slug sub kategori',
            'description' => 'Deskripsi',
            'is_active' => 'Status',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori wajib dipilih.',
            'code.required' => 'Kode sub kategori wajib diisi.',
            'name.required' => 'Nama sub kategori wajib diisi.',
        ];
    }
}
