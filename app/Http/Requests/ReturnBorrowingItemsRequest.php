<?php

namespace App\Http\Requests;

use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReturnBorrowingItemsRequest extends FormRequest
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
            'return_all' => ['nullable', 'boolean'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('return_all')) {
                return;
            }

            $ids = array_filter(array_map('intval', (array) $this->input('item_ids', [])));
            if ($ids === []) {
                $validator->errors()->add('item_ids', 'Pilih minimal satu barang untuk dikembalikan, atau kembalikan semua.');
            }
        });
    }
}
