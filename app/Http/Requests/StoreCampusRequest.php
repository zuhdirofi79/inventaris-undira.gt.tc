<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('location.manage') ?? false;
    }

    public function rules(): array
    {
        $campusId = $this->route('campus')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('campuses', 'code')->ignore($campusId)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('campuses', 'slug')->ignore($campusId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Kode kampus',
            'name' => 'Nama kampus',
            'slug' => 'Slug kampus',
            'description' => 'Deskripsi',
            'is_active' => 'Status',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode kampus wajib diisi.',
            'code.unique' => 'Kode kampus sudah digunakan.',
            'name.required' => 'Nama kampus wajib diisi.',
            'slug.unique' => 'Slug kampus sudah digunakan.',
            'is_active.required' => 'Status kampus wajib dipilih.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
