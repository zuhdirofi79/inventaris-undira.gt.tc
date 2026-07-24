<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'approval_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
