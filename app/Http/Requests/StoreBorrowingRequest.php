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
            'borrower_phone' => ['required', 'string', 'max:50', 'regex:/^(?:\+62|62|0)[\s.\-]*8[1-9](?:[\s.\-]*[0-9]){6,11}$/'],
            'borrow_date' => ['required', 'date'],
            'expected_return_date' => ['required', 'date', 'after_or_equal:borrow_date'],
            'notes' => ['required', 'string'],
            'status' => ['nullable', Rule::enum(BorrowingStatus::class)],
            'supporting_document' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:5120',
            ],
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

            $file = $this->file('supporting_document');
            if ($file && ! $validator->errors()->has('supporting_document')) {
                $ext = strtolower((string) $file->getClientOriginalExtension());
                if ($ext !== 'pdf') {
                    $validator->errors()->add('supporting_document', 'Dokumen pendukung harus berupa PDF.');
                }
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
            'borrower_phone.required' => 'Nomor Telepon / No. HP wajib diisi.',
            'borrower_phone.regex' => 'Nomor Telepon / No. HP tidak valid. Gunakan format 08xxxxxxxxxx, 628xxxxxxxxxx, atau +628xxxxxxxxxx.',
            'borrow_date.required' => 'Tanggal pinjam wajib diisi.',
            'expected_return_date.required' => 'Tanggal rencana kembali wajib diisi.',
            'expected_return_date.after_or_equal' => 'Tanggal rencana kembali tidak boleh sebelum tanggal pinjam.',
            'notes.required' => 'Keperluan wajib diisi.',
            'supporting_document.mimes' => 'Dokumen pendukung harus berupa PDF.',
            'supporting_document.max' => 'Ukuran dokumen maksimal 5 MB.',
            'supporting_document.file' => 'Dokumen pendukung tidak valid.',
        ];
    }
}
