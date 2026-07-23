<?php

namespace App\Http\Requests;

use App\Models\StockOpname;
use Illuminate\Foundation\Http\FormRequest;

class ScanStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockOpname|null $stockOpname */
        $stockOpname = $this->route('stockOpname');

        return $stockOpname instanceof StockOpname
            && ($this->user()?->can('update', $stockOpname) ?? false);
    }

    public function rules(): array
    {
        return [
            'inventory_number' => ['required', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'inventory_number' => 'Nomor inventaris',
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_number.required' => 'Nomor inventaris wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('inventory_number')) {
            $this->merge([
                'inventory_number' => trim((string) $this->input('inventory_number')),
            ]);
        }
    }
}
