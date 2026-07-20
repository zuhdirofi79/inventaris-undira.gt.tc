<?php

namespace App\Http\Requests;

use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('location.manage') ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('room') !== null;

        return [
            'floor_id' => [$isUpdate ? 'sometimes' : 'required', ActiveExists::rule('floors')],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'room_type' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
