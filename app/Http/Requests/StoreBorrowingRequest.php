<?php

namespace App\Http\Requests;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use App\Http\Requests\Concerns\BindsAuthenticatedBorrowerEmail;
use App\Http\Requests\Concerns\ValidatesSupportingDocument;
use App\Rules\IndonesianPhoneNumber;
use App\Services\BorrowingItemResolver;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreBorrowingRequest extends FormRequest
{
    use BindsAuthenticatedBorrowerEmail;
    use ValidatesSupportingDocument;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Borrowing::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->bindBorrowerIdentityForCreate();

        if ($this->has('borrower_affiliation')) {
            $this->merge([
                'borrower_affiliation' => trim(strip_tags((string) $this->input('borrower_affiliation'))),
            ]);
        }
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
            'borrower_email' => ['required', 'email:rfc', 'max:255'],
            'borrower_phone' => ['required', 'string', 'max:50', new IndonesianPhoneNumber],
            'borrower_affiliation' => ['required', 'string', 'max:255'],
            'borrow_date' => ['required', 'date_format:Y-m-d\TH:i'],
            'expected_return_date' => ['required', 'date_format:Y-m-d\TH:i', 'after:borrow_date'],
            'notes' => ['required', 'string'],
            'status' => ['nullable', Rule::enum(BorrowingStatus::class)],
            'supporting_document' => $this->supportingDocumentRules(true),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->guardSupportingDocument($validator);

            $hasItems = $this->filled('items')
                || $this->filled('inventory_item_ids')
                || $this->filled('inventory_item_id');

            if (! $hasItems) {
                $validator->errors()->add('items', 'Tambahkan minimal satu inventaris sebelum menyimpan peminjaman.');

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
            'items.required' => 'Tambahkan minimal satu inventaris sebelum menyimpan peminjaman.',
            'borrower_name.required' => $this->canProxyBorrowerIdentity()
                ? 'Nama peminjam wajib diisi.'
                : 'Akun Anda belum memiliki nama. Perbarui profil sebelum mengajukan peminjaman.',
            'borrower_email.required' => $this->canProxyBorrowerIdentity()
                ? 'Email peminjam wajib diisi.'
                : 'Akun Anda belum memiliki email yang valid. Perbarui profil sebelum mengajukan peminjaman.',
            'borrower_email.email' => $this->canProxyBorrowerIdentity()
                ? 'Format email tidak valid.'
                : 'Email akun Anda tidak valid. Perbarui profil sebelum mengajukan peminjaman.',
            'items.*.inventory_item_id.required_with' => 'Barang inventaris wajib dipilih.',
            'borrower_phone.required' => 'Nomor Telepon / No. HP wajib diisi.',
            'borrower_phone.regex' => 'Nomor Telepon / No. HP tidak valid. Awali dengan 08, 62, atau +62, lalu isi minimal 4 digit. Spasi dan tanda hubung diperbolehkan.',
            'borrower_affiliation.required' => 'Kolom Unit wajib diisi.',
            'borrower_affiliation.max' => 'Kolom Unit maksimal 255 karakter.',
            'borrow_date.required' => 'Tanggal dan jam pinjam wajib diisi.',
            'borrow_date.date_format' => 'Tanggal dan jam pinjam tidak valid.',
            'expected_return_date.required' => 'Tanggal dan jam kembali wajib diisi.',
            'expected_return_date.date_format' => 'Tanggal dan jam kembali tidak valid.',
            'expected_return_date.after' => 'Tanggal dan jam pengembalian harus setelah tanggal dan jam peminjaman.',
            'notes.required' => 'Keperluan wajib diisi.',
            ...$this->supportingDocumentMessages(),
        ];
    }
}
