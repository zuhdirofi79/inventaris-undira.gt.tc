<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceType;
use App\Models\MaintenanceSchedule;
use App\Services\MaintenanceScheduleService;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'next_maintenance_date' => ['nullable', 'date'],
            'remind_before_days' => ['required', 'integer', Rule::in([7, 14, 30])],
            'responsible_pic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $itemId = (int) $this->input('inventory_item_id');
            $type = (string) $this->input('maintenance_type');
            $dup = app(MaintenanceScheduleService::class)->findDuplicateActive($itemId, $type);

            if ($dup) {
                $validator->errors()->add(
                    'maintenance_type',
                    'Jadwal aktif jenis ini sudah ada untuk inventaris tersebut (jadwal #'.$dup->id.'). Edit jadwal yang ada atau nonaktifkan dulu.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.required' => 'Inventaris wajib dipilih.',
            'maintenance_type.required' => 'Jenis pemeliharaan wajib dipilih.',
            'interval_value.required' => 'Interval wajib diisi.',
            'interval_unit.required' => 'Satuan interval wajib dipilih.',
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
