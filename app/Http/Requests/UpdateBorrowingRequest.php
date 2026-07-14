<?php

namespace App\Http\Requests;

use App\Enums\BorrowingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('borrowing.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'borrower_name' => ['sometimes', 'required', 'string', 'max:255'],
            'borrower_email' => ['nullable', 'email', 'max:255'],
            'borrower_phone' => ['nullable', 'string', 'max:50'],
            'borrow_date' => ['sometimes', 'required', 'date'],
            'return_date' => ['nullable', 'date'],
            'expected_return_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', Rule::enum(BorrowingStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
