<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartRepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'service_date' => ['required', 'date'],
            'technician' => ['nullable', 'string', 'max:255'],
            'technician_user_id' => ['nullable', 'exists:users,id'],
            'damage_type' => ['required', 'string', 'max:255'],
            'analysis' => ['required', 'string'],
            'work_performed' => ['nullable', 'string'],
            'parts_replaced' => ['nullable', 'string'],
            'estimated_completion' => ['nullable', 'date', 'after_or_equal:service_date'],
            'notes' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'photos_before' => ['nullable', 'array', 'max:10'],
            'photos_before.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'damage_type.required' => 'Jenis kerusakan wajib diisi.',
            'analysis.required' => 'Analisa wajib diisi.',
            'service_date.required' => 'Tanggal wajib diisi.',
            'photos_before.*.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WEBP.',
            'photos_before.*.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'technician_user_id' => $this->filled('technician_user_id')
                ? $this->input('technician_user_id')
                : $this->user()?->id,
            'technician' => $this->filled('technician')
                ? $this->input('technician')
                : $this->user()?->name,
        ]);
    }
}
