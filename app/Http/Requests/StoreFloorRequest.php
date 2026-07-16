<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFloorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super-admin') ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('floor') !== null;

        return [
            'building_id' => [$isUpdate ? 'sometimes' : 'required', 'exists:buildings,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'floor_number' => ['required', 'integer', 'min:0', 'max:99'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
