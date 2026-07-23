<?php

namespace App\Http\Requests;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Borrowing|null $borrowing */
        $borrowing = $this->route('borrowing');

        return $borrowing instanceof Borrowing
            && ($this->user()?->can('update', $borrowing) ?? false);
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
