<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceStatus;
use App\Models\Maintenance;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Maintenance::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', ActiveExists::rule('inventory_items')],
            'vendor_id' => ['nullable', ActiveExists::rule('vendors')],
            'service_date' => ['required', 'date'],
            'next_schedule' => ['nullable', 'date', 'after_or_equal:service_date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(MaintenanceStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.required' => 'Barang inventaris wajib dipilih.',
            'service_date.required' => 'Tanggal servis wajib diisi.',
            'next_schedule.after_or_equal' => 'Jadwal berikutnya tidak boleh sebelum tanggal servis.',
            'cost.min' => 'Biaya tidak boleh negatif.',
        ];
    }
}
