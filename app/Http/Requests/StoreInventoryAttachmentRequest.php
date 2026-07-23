<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,csv,txt'],
            'type' => ['required', Rule::in(['invoice', 'manual_book', 'warranty', 'document'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'Berkas lampiran',
            'type' => 'Jenis lampiran',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Berkas lampiran wajib diunggah.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
            'file.mimes' => 'Format berkas tidak didukung.',
            'type.required' => 'Jenis lampiran wajib dipilih.',
            'type.in' => 'Jenis lampiran tidak valid.',
        ];
    }
}
