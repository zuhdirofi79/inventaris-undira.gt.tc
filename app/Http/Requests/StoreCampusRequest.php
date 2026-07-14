<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super-admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:campuses,code'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:campuses,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
