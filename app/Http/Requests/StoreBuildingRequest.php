<?php

namespace App\Http\Requests;

use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('location.manage') ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('building') !== null;

        return [
            'campus_id' => [$isUpdate ? 'sometimes' : 'required', ActiveExists::rule('campuses')],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'campus_id' => 'Kampus',
            'code' => 'Kode gedung',
            'name' => 'Nama gedung',
            'slug' => 'Slug gedung',
            'description' => 'Deskripsi',
            'is_active' => 'Status',
        ];
    }

    public function messages(): array
    {
        return [
            'campus_id.required' => 'Kampus wajib dipilih.',
            'code.required' => 'Kode gedung wajib diisi.',
            'name.required' => 'Nama gedung wajib diisi.',
            'is_active.required' => 'Status gedung wajib dipilih.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
