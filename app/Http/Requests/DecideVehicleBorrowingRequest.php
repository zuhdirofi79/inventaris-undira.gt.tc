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
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
            'assignment' => ['required_if:decision,approve', 'nullable', Rule::in(['fleet', 'gocar'])],
            'vehicle_id' => ['required_if:assignment,fleet', 'nullable', 'integer'],
            'driver_id' => ['required_if:assignment,fleet', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan approval wajib dipilih.',
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi.',
            'assignment.required_if' => 'Penugasan kendaraan atau GoCar wajib dipilih.',
            'vehicle_id.required_if' => 'Kendaraan wajib dipilih.',
            'driver_id.required_if' => 'Pengemudi wajib dipilih.',
        ];
    }
}
