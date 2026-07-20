<?php

namespace App\Http\Requests;

use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;

class DeletionApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deletion-approval.request') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', ActiveExists::rule('inventory_items')],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
