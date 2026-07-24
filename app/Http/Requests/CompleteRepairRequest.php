<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteRepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'completed_at' => ['required', 'date'],
            'work_performed' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'photos_after' => ['nullable', 'array', 'max:10'],
            'photos_after.*' => ['image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'work_performed.required' => 'Pekerjaan yang dilakukan wajib diisi.',
            'completed_at.required' => 'Tanggal selesai wajib diisi.',
        ];
    }
}
