<?php

namespace App\Http\Requests;

use App\Models\StockOpname;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockOpname::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'campus_id' => ['required', ActiveExists::rule('campuses')],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Judul stock opname',
            'campus_id' => 'Kampus',
            'notes' => 'Catatan',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul stock opname wajib diisi.',
            'campus_id.required' => 'Kampus wajib dipilih.',
            'campus_id.exists' => 'Kampus tidak valid atau tidak aktif.',
        ];
    }
}
