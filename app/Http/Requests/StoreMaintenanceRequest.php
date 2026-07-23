<?php

namespace App\Http\Requests;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceResult;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
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
            'maintenance_type' => ['required', Rule::enum(MaintenanceType::class)],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],
            'technician' => ['nullable', 'string', 'max:255'],
            'service_date' => ['required', 'date'],
            'next_schedule' => ['nullable', 'date', 'after_or_equal:service_date'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'parts_replaced' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(MaintenanceStatus::class)],
            'result' => ['nullable', Rule::enum(MaintenanceResult::class)],
            'photos_before' => ['nullable', 'array', 'max:10'],
            'photos_before.*' => ['image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.required' => 'Barang inventaris wajib dipilih.',
            'maintenance_type.required' => 'Jenis pemeliharaan wajib dipilih.',
            'priority.required' => 'Prioritas wajib dipilih.',
            'service_date.required' => 'Tanggal pemeliharaan wajib diisi.',
            'next_schedule.after_or_equal' => 'Jadwal berikutnya tidak boleh sebelum tanggal pemeliharaan.',
            'cost.min' => 'Biaya aktual tidak boleh negatif.',
            'estimated_cost.min' => 'Estimasi biaya tidak boleh negatif.',
            'photos_before.*.image' => 'File foto sebelum perbaikan harus berupa gambar.',
            'photos_before.*.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
