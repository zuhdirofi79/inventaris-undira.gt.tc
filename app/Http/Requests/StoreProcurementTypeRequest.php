<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcurementTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.manage') ?? false;
    }

    public function rules(): array
    {
        $typeId = $this->route('procurementType')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('procurement_types', 'code')->ignore($typeId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
