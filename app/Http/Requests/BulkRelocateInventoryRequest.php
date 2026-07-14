<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkRelocateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_ids' => ['required', 'array', 'min:1'],
            'inventory_ids.*' => ['integer', 'exists:inventory_items,id'],
            'to_room_id' => ['required', 'exists:rooms,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
