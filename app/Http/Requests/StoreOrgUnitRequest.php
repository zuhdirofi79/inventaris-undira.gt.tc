<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('unit.manage') ?? false;
    }

    public function rules(): array
    {
        $orgUnitId = $this->route('orgUnit')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('org_units', 'code')->ignore($orgUnitId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode unit organisasi wajib diisi.',
            'code.unique' => 'Kode unit organisasi sudah digunakan.',
            'name.required' => 'Nama unit organisasi wajib diisi.',
        ];
    }
}
