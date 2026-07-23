<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceType;
use App\Models\MaintenanceSchedule;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MaintenanceSchedule::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', ActiveExists::rule('inventory_items')],
            'maintenance_type' => ['required', Rule::enum(MaintenanceType::class)],
            'interval_value' => ['required', 'integer', 'min:1', 'max:3650'],
            'interval_unit' => ['required', Rule::enum(MaintenanceIntervalUnit::class)],
            'next_maintenance_date' => ['required', 'date'],
            'remind_before_days' => ['required', 'integer', Rule::in([7, 14, 30])],
            'responsible_pic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.required' => 'Inventaris wajib dipilih.',
            'maintenance_type.required' => 'Jenis pemeliharaan wajib dipilih.',
            'interval_value.required' => 'Interval wajib diisi.',
            'interval_unit.required' => 'Satuan interval wajib dipilih.',
            'next_maintenance_date.required' => 'Tanggal pemeliharaan berikutnya wajib diisi.',
            'remind_before_days.in' => 'Pengingat harus 7, 14, atau 30 hari.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
