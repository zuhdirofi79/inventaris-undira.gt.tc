<?php

namespace App\Http\Requests;

use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;

class ApproveBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Borrowing|null $borrowing */
        $borrowing = $this->route('borrowing');

        return $borrowing instanceof Borrowing
            && ($this->user()?->can('approve', $borrowing) ?? false);
    }

    public function rules(): array
    {
        return [
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
