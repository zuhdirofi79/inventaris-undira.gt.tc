<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public const DUPLICATE_EMAIL_MESSAGE = 'Email ini sudah terdaftar. Silakan gunakan email lain atau masuk menggunakan akun Anda.';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'email' => [
                'required',
                'email',
                'max:255',
                // Table-based unique includes soft-deleted rows so trashed accounts
                // cannot be re-registered while users.email UNIQUE still holds.
                Rule::unique('users', 'email'),
                'regex:/^[A-Za-z0-9._%+-]+@undira\.ac\.id$/i',
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.regex' => 'Email harus menggunakan domain @undira.ac.id.',
            'email.unique' => self::DUPLICATE_EMAIL_MESSAGE,
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }

        if ($this->has('identity_number') && ! $this->filled('identity_number')) {
            $this->merge(['identity_number' => null]);
        }
    }
}
