<?php

namespace App\Http\Requests;

use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreFloorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('location.manage') ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('floor') !== null;

        return [
            'building_id' => [$isUpdate ? 'sometimes' : 'required', ActiveExists::rule('buildings')],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'floor_number' => ['required', 'integer', 'min:0', 'max:99'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'building_id' => 'Gedung',
            'code' => 'Kode lantai',
            'name' => 'Nama lantai',
            'floor_number' => 'Nomor lantai',
            'is_active' => 'Status',
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.required' => 'Gedung wajib dipilih.',
            'code.required' => 'Kode lantai wajib diisi.',
            'name.required' => 'Nama lantai wajib diisi.',
            'floor_number.required' => 'Nomor lantai wajib diisi.',
            'is_active.required' => 'Status lantai wajib dipilih.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
