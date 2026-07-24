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

class UpdateMaintenanceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MaintenanceSchedule|null $schedule */
        $schedule = $this->route('maintenance_schedule');

        return $schedule instanceof MaintenanceSchedule
            && ($this->user()?->can('update', $schedule) ?? false);
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['sometimes', 'required', ActiveExists::rule('inventory_items')],
            'maintenance_type' => ['sometimes', 'required', Rule::enum(MaintenanceType::class)],
            'interval_value' => ['sometimes', 'required', 'integer', 'min:1', 'max:3650'],
            'interval_unit' => ['sometimes', 'required', Rule::enum(MaintenanceIntervalUnit::class)],
            'next_maintenance_date' => ['sometimes', 'required', 'date'],
            'remind_before_days' => ['sometimes', 'required', 'integer', Rule::in([7, 14, 30])],
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

            /** @var MaintenanceSchedule $schedule */
            $schedule = $this->route('maintenance_schedule');
            $itemId = (int) $this->input('inventory_item_id', $schedule->inventory_item_id);
            $type = (string) $this->input('maintenance_type', $schedule->maintenance_type?->value);
            $active = $this->has('is_active')
                ? $this->boolean('is_active')
                : (bool) $schedule->is_active;

            if (! $active) {
                return;
            }

            $dup = app(MaintenanceScheduleService::class)->findDuplicateActive($itemId, $type, $schedule->id);
            if ($dup) {
                $validator->errors()->add(
                    'maintenance_type',
                    'Jadwal aktif jenis ini sudah ada untuk inventaris tersebut (jadwal #'.$dup->id.').'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'remind_before_days.in' => 'Pengingat harus 7, 14, atau 30 hari.',
            'next_maintenance_date.required' => 'Tanggal pemeliharaan berikutnya wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }
}
