<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideRoomBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('room-borrowing.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan approval wajib dipilih.',
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi.',
        ];
    }
}
