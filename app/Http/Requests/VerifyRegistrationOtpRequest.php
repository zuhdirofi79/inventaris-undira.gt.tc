<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRegistrationOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'otp' => ['required', 'digits:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.digits' => 'Kode OTP harus 4 digit angka.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('otp')) {
            $this->merge([
                'otp' => preg_replace('/\D+/', '', (string) $this->input('otp')),
            ]);
        }
    }
}
