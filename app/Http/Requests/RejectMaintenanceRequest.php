<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Alasan pengembalian ke teknisi wajib diisi.',
        ];
    }
}
