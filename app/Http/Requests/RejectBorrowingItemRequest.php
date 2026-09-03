<?php

namespace App\Http\Requests;

use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;

class RejectBorrowingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Borrowing|null $borrowing */
        $borrowing = $this->route('borrowing');

        return $borrowing instanceof Borrowing
            && ($this->user()?->can('reject', $borrowing) ?? false);
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min' => 'Alasan penolakan minimal 3 karakter.',
        ];
    }
}
