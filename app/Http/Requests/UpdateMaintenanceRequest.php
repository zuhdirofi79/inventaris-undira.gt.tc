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

class UpdateMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Maintenance|null $maintenance */
        $maintenance = $this->route('maintenance');

        return $maintenance instanceof Maintenance
            && ($this->user()?->can('update', $maintenance) ?? false);
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['nullable', ActiveExists::rule('vendors')],
            'maintenance_type' => ['sometimes', 'required', Rule::enum(MaintenanceType::class)],
            'priority' => ['sometimes', 'required', Rule::enum(MaintenancePriority::class)],
            'technician' => ['nullable', 'string', 'max:255'],
            'service_date' => ['sometimes', 'required', 'date'],
            'next_schedule' => ['nullable', 'date', 'after_or_equal:service_date'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'parts_replaced' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::enum(MaintenanceStatus::class)],
            'result' => [
                'nullable',
                Rule::enum(MaintenanceResult::class),
                Rule::requiredIf(fn () => $this->input('status') === MaintenanceStatus::Completed->value),
            ],
            'photos_during' => ['nullable', 'array', 'max:10'],
            'photos_during.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photos_after' => ['nullable', 'array', 'max:10'],
            'photos_after.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'vendor_id' => 'Vendor',
            'service_date' => 'Tanggal pemeliharaan',
            'next_schedule' => 'Jadwal berikutnya',
            'cost' => 'Biaya aktual',
            'estimated_cost' => 'Estimasi biaya',
            'description' => 'Deskripsi',
            'status' => 'Status',
            'result' => 'Hasil',
            'technician' => 'Teknisi',
        ];
    }

    public function messages(): array
    {
        return [
            'service_date.required' => 'Tanggal pemeliharaan wajib diisi.',
            'next_schedule.after_or_equal' => 'Jadwal berikutnya tidak boleh sebelum tanggal pemeliharaan.',
            'cost.min' => 'Biaya aktual tidak boleh negatif.',
            'status.required' => 'Status maintenance wajib dipilih.',
            'status.enum' => 'Status maintenance tidak valid.',
            'result.required' => 'Hasil pemeliharaan wajib diisi saat status Selesai.',
            'vendor_id.exists' => 'Vendor tidak valid atau tidak aktif.',
        ];
    }
}
