<?php

namespace App\Http\Requests;

use App\Enums\BorrowingStatus;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('borrowing.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', ActiveExists::rule('inventory_items')],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_email' => ['nullable', 'email', 'max:255'],
            'borrower_phone' => ['nullable', 'string', 'max:50'],
            'borrow_date' => ['required', 'date'],
            'expected_return_date' => ['required', 'date', 'after_or_equal:borrow_date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(BorrowingStatus::class)],
        ];
    }
}
