<?php

namespace App\Http\Requests;

use App\Models\Maintenance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenancePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Maintenance|null $maintenance */
        $maintenance = $this->route('maintenance');

        return $maintenance instanceof Maintenance
            && ($this->user()?->can('update', $maintenance) ?? false);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['before', 'during', 'after'])],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis foto wajib dipilih.',
            'type.in' => 'Jenis foto tidak valid.',
            'photos.required' => 'Pilih minimal satu foto.',
            'photos.*.image' => 'File harus berupa gambar.',
            'photos.*.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WEBP.',
            'photos.*.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
