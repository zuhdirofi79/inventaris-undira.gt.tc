<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.approve') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rejection_reason')) {
            $this->merge([
                'rejection_reason' => trim((string) $this->input('rejection_reason')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:3', 'max:2000'],
            '_reject_url' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Alasan pengembalian ke teknisi wajib diisi.',
            'rejection_reason.min' => 'Alasan revisi minimal 3 karakter.',
        ];
    }
}
