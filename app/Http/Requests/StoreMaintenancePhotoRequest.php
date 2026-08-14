<?php

namespace App\Http\Requests;

use App\Models\Maintenance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMaintenancePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Maintenance|null $maintenance */
        $maintenance = $this->route('maintenance');

        return $maintenance instanceof Maintenance
            && ($this->user()?->can('update', $maintenance) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('type')) {
            $this->merge(['type' => 'before']);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['before', 'during', 'after'])],
            'photos' => ['required', 'array', 'min:1', 'max:1'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Maintenance|null $maintenance */
            $maintenance = $this->route('maintenance');
            if (! $maintenance instanceof Maintenance) {
                return;
            }

            if ($maintenance->photos()->exists()) {
                $validator->errors()->add(
                    'photos',
                    'Work order ini sudah memiliki foto. Hapus foto yang ada terlebih dahulu jika ingin mengganti.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Jenis foto tidak valid.',
            'photos.required' => 'Pilih satu foto.',
            'photos.max' => 'Hanya boleh mengunggah 1 foto per work order.',
            'photos.*.image' => 'File harus berupa gambar.',
            'photos.*.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WEBP.',
            'photos.*.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
