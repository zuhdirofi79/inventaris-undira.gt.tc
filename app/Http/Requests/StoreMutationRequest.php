<?php

namespace App\Http\Requests;

use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('mutation.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', ActiveExists::rule('inventory_items')],
            'to_room_id' => ['required', ActiveExists::rule('rooms')],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
