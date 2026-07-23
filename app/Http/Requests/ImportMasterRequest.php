<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Berkas import wajib diunggah.',
            'file.mimes' => 'Berkas harus berformat CSV atau Excel.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
        ];
    }
}
