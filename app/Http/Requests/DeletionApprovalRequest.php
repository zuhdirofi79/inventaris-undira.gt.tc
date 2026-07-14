<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeletionApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.delete') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
