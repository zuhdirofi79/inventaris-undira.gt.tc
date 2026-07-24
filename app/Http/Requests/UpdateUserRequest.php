<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['sometimes', 'required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama pengguna',
            'email' => 'Email',
            'phone' => 'Telepon',
            'is_active' => 'Status aktif',
            'roles' => 'Peran',
            'roles.*' => 'Peran',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengguna wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'roles.required' => 'Peran wajib dipilih.',
            'roles.min' => 'Minimal satu peran harus dipilih.',
            'roles.*.exists' => 'Peran yang dipilih tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }

        // Checkbox: absent means false (same as Request::boolean('is_active')).
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
