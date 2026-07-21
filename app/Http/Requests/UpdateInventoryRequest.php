<?php

namespace App\Http\Requests;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Support\Validation\ActiveExists;
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
            'category_id' => ['sometimes', 'required', ActiveExists::rule('categories')],
            'sub_category_id' => ['nullable', ActiveExists::rule('sub_categories')],
            'room_id' => ['sometimes', 'required', ActiveExists::rule('rooms')],
            'brand' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'specification' => ['nullable', 'string'],
            'purchase_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'purchase_date' => ['nullable', 'date'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'procurement_type_id' => ['nullable', ActiveExists::rule('procurement_types')],
            'price' => ['nullable', 'numeric', 'min:0'],
            'depreciation_value' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['sometimes', 'required', Rule::enum(InventoryCondition::class)],
            'status' => ['sometimes', 'required', Rule::enum(InventoryStatus::class)],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'org_unit_id' => ['nullable', ActiveExists::rule('org_units')],
            'supplier_id' => ['nullable', ActiveExists::rule('suppliers')],
            'vendor_id' => ['nullable', ActiveExists::rule('vendors')],
            'warranty_info' => ['nullable', 'string'],
            'warranty_expiry' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'price' => $this->filled('price') ? $this->input('price') : null,
            'depreciation_value' => $this->filled('depreciation_value') ? $this->input('depreciation_value') : null,
        ]);
    }
}
