<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super-admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'floor_id' => ['required', 'exists:floors,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'room_type' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
