<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'image', 'max:5120'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'photos' => 'Foto',
            'photos.*' => 'Foto',
            'is_primary' => 'Foto utama',
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required' => 'Pilih minimal satu foto.',
            'photos.min' => 'Pilih minimal satu foto.',
            'photos.max' => 'Maksimal 10 foto per unggahan.',
            'photos.*.image' => 'Berkas harus berupa gambar.',
            'photos.*.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_primary' => $this->boolean('is_primary'),
        ]);
    }
}
