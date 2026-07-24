<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user instanceof User
            && ($this->user()?->can('update', $user) ?? false);
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
            'must_change_password' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'Password baru',
            'password_confirmation' => 'Konfirmasi password',
            'must_change_password' => 'Wajib ganti password',
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'must_change_password' => $this->boolean('must_change_password'),
        ]);
    }
}
