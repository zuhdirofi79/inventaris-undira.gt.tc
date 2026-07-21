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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
