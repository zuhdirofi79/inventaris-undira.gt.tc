<?php

namespace App\Http\Requests;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'room_id' => ['sometimes', 'required', 'exists:rooms,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'specification' => ['nullable', 'string'],
            'purchase_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'purchase_date' => ['nullable', 'date'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'depreciation_value' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['sometimes', 'required', Rule::enum(InventoryCondition::class)],
            'status' => ['sometimes', 'required', Rule::enum(InventoryStatus::class)],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'warranty_info' => ['nullable', 'string'],
            'warranty_expiry' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:50'],
        ];
    }
}
