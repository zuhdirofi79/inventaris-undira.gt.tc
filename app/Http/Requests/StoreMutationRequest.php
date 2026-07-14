<?php

namespace App\Http\Requests;

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
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'to_room_id' => ['required', 'exists:rooms,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
