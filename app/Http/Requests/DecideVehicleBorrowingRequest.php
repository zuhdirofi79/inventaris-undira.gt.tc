<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideVehicleBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vehicle-borrowing.approve') ?? false;
    }

    public function rules(): array
    {
        $approvingFleet = $this->input('decision') === 'approve'
            && $this->input('assignment') === 'fleet';

        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
            'assignment' => ['required_if:decision,approve', 'nullable', Rule::in(['fleet', 'gocar'])],
            'vehicle_id' => [Rule::requiredIf($approvingFleet), 'nullable', 'integer'],
            'driver_id' => [Rule::requiredIf($approvingFleet), 'nullable', 'integer'],
            'acknowledge_conflict' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan approval wajib dipilih.',
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi.',
            'assignment.required_if' => 'Penugasan kendaraan atau GoCar wajib dipilih.',
            'vehicle_id.required' => 'Kendaraan wajib dipilih.',
            'driver_id.required' => 'Pengemudi wajib dipilih.',
            'acknowledge_conflict.boolean' => 'Konfirmasi konflik tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('decision') === 'reject') {
            $this->merge([
                'assignment' => null,
                'vehicle_id' => null,
                'driver_id' => null,
                'acknowledge_conflict' => null,
            ]);

            return;
        }

        if ($this->has('acknowledge_conflict')) {
            $this->merge([
                'acknowledge_conflict' => filter_var($this->input('acknowledge_conflict'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
