<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceStatus;
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
            'service_date' => ['sometimes', 'required', 'date'],
            'next_schedule' => ['nullable', 'date', 'after_or_equal:service_date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::enum(MaintenanceStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'vendor_id' => 'Vendor',
            'service_date' => 'Tanggal servis',
            'next_schedule' => 'Jadwal berikutnya',
            'cost' => 'Biaya',
            'description' => 'Deskripsi',
            'status' => 'Status',
        ];
    }

    public function messages(): array
    {
        return [
            'service_date.required' => 'Tanggal servis wajib diisi.',
            'next_schedule.after_or_equal' => 'Jadwal berikutnya tidak boleh sebelum tanggal servis.',
            'cost.min' => 'Biaya tidak boleh negatif.',
            'status.required' => 'Status maintenance wajib dipilih.',
            'status.enum' => 'Status maintenance tidak valid.',
            'vendor_id.exists' => 'Vendor tidak valid atau tidak aktif.',
        ];
    }
}
