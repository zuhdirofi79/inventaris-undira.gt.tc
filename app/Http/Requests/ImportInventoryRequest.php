<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.import') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'preview' => ['nullable', 'boolean'],
        ];
    }
}
