<?php

namespace App\Http\Requests;

use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;

class HandoverBorrowingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Borrowing|null $borrowing */
        $borrowing = $this->route('borrowing');

        return $borrowing instanceof Borrowing
            && ($this->user()?->can('handover', $borrowing) ?? false);
    }

    public function rules(): array
    {
        return [
            'borrowed_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'borrowed_at.date' => 'Tanggal dan jam peminjaman aktual tidak valid.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->exists('borrowed_at') && blank($this->input('borrowed_at'))) {
                $validator->errors()->add('borrowed_at', 'Tanggal dan jam peminjaman aktual wajib diisi.');
            }
        });
    }
}
