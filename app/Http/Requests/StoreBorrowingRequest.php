<?php

namespace App\Http\Requests;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use App\Services\BorrowingItemResolver;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Borrowing::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Legacy single-item (still accepted).
            'inventory_item_id' => ['nullable', ActiveExists::rule('inventory_items')],
            // Explicit multi-item IDs.
            'inventory_item_ids' => ['nullable', 'array', 'min:1'],
            'inventory_item_ids.*' => ['integer', ActiveExists::rule('inventory_items')],
            // Cart lines with optional qty expansion.
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required_with:items', 'integer', ActiveExists::rule('inventory_items')],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:500'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_email' => ['nullable', 'email', 'max:255'],
            'borrower_phone' => ['nullable', 'string', 'max:50'],
            'borrow_date' => ['required', 'date'],
            'expected_return_date' => ['required', 'date', 'after_or_equal:borrow_date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(BorrowingStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasItems = $this->filled('items')
                || $this->filled('inventory_item_ids')
                || $this->filled('inventory_item_id');

            if (! $hasItems) {
                $validator->errors()->add('items', 'Minimal satu barang inventaris harus ditambahkan.');

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                app(BorrowingItemResolver::class)->normalizeRequestItems($this->all());
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.required' => 'Barang inventaris wajib dipilih.',
            'items.required' => 'Minimal satu barang inventaris harus ditambahkan.',
            'items.*.inventory_item_id.required_with' => 'Barang inventaris wajib dipilih.',
            'borrower_name.required' => 'Nama peminjam wajib diisi.',
            'borrow_date.required' => 'Tanggal pinjam wajib diisi.',
            'expected_return_date.required' => 'Tanggal rencana kembali wajib diisi.',
            'expected_return_date.after_or_equal' => 'Tanggal rencana kembali tidak boleh sebelum tanggal pinjam.',
        ];
    }
}
