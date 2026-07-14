<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'service_date' => ['required', 'date'],
            'next_schedule' => ['nullable', 'date', 'after_or_equal:service_date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(MaintenanceStatus::class)],
        ];
    }
}
