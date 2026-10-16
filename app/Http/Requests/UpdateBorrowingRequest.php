<?php

namespace App\Http\Requests;

use App\Enums\BorrowingStatus;
use App\Http\Requests\Concerns\BindsAuthenticatedBorrowerEmail;
use App\Http\Requests\Concerns\ValidatesSupportingDocument;
use App\Models\Borrowing;
use App\Rules\IndonesianPhoneNumber;
use App\Services\BorrowingSupportingDocumentService;
use App\Services\InventoryAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateBorrowingRequest extends FormRequest
{
    use BindsAuthenticatedBorrowerEmail;
    use ValidatesSupportingDocument;

    public function authorize(): bool
    {
        /** @var Borrowing|null $borrowing */
        $borrowing = $this->route('borrowing');

        return $borrowing instanceof Borrowing
            && ($this->user()?->can('update', $borrowing) ?? false);
    }

    protected function prepareForValidation(): void
    {
        /** @var Borrowing|null $borrowing */
        $borrowing = $this->route('borrowing');

        if ($borrowing instanceof Borrowing) {
            // Non-admin editors keep historical identity; admin/super-admin may update it.
            $this->bindBorrowerIdentityForUpdate(
                (string) $borrowing->borrower_name,
                $borrowing->borrower_email,
            );
        }
    }

    public function rules(): array
    {
        return [
            'borrower_name' => ['sometimes', 'required', 'string', 'max:255'],
            'borrower_email' => ['required', 'email:rfc', 'max:255'],
            'borrower_phone' => ['required', 'string', 'max:50', new IndonesianPhoneNumber],
            'borrow_date' => ['sometimes', 'required', 'date_format:Y-m-d\TH:i'],
            'return_date' => ['nullable', 'date'],
            'expected_return_date' => ['sometimes', 'required', 'date_format:Y-m-d\TH:i', 'after:borrow_date'],
            'status' => ['sometimes', 'required', Rule::enum(BorrowingStatus::class)],
            'notes' => ['required', 'string'],
            'supporting_document' => $this->supportingDocumentRules(),
            'remove_supporting_document' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->guardSupportingDocument($validator);
            $this->requireStoredOrReplacementDocument($validator);

            if ($validator->errors()->isNotEmpty() || ! $this->filled('borrow_date') || ! $this->filled('expected_return_date')) {
                return;
            }

            $borrowing = $this->route('borrowing');
            if (! $borrowing instanceof Borrowing) {
                return;
            }

            $start = Carbon::createFromFormat('Y-m-d\TH:i', (string) $this->input('borrow_date'));
            $end = Carbon::createFromFormat('Y-m-d\TH:i', (string) $this->input('expected_return_date'));
            if (! $start instanceof Carbon || ! $end instanceof Carbon) {
                return;
            }

            $ids = $borrowing->items()->pluck('inventory_item_id')->map(fn ($id) => (int) $id)->all();
            if ($borrowing->inventory_item_id) {
                $ids[] = (int) $borrowing->inventory_item_id;
            }

            try {
                app(InventoryAvailability::class)->assertSelectable($ids, $start, $end, (int) $borrowing->id);
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
            'borrow_date.required' => 'Tanggal dan jam pinjam wajib diisi.',
            'borrow_date.date_format' => 'Tanggal dan jam pinjam tidak valid.',
            'expected_return_date.required' => 'Tanggal dan jam kembali wajib diisi.',
            'expected_return_date.date_format' => 'Tanggal dan jam kembali tidak valid.',
            'expected_return_date.after' => 'Tanggal dan jam pengembalian harus setelah tanggal dan jam peminjaman.',
            'borrower_phone.required' => 'Nomor Telepon / No. HP wajib diisi.',
            'borrower_phone.regex' => 'Nomor Telepon / No. HP tidak valid. Awali dengan 08, 62, atau +62, lalu isi minimal 4 digit. Spasi dan tanda hubung diperbolehkan.',
            'notes.required' => 'Keperluan wajib diisi.',
            'borrower_name.required' => 'Nama peminjam wajib diisi.',
            'borrower_email.required' => 'Email peminjam wajib diisi.',
            'borrower_email.email' => 'Format email tidak valid.',
            ...$this->supportingDocumentMessages(),
        ];
    }

    protected function requireStoredOrReplacementDocument(Validator $validator): void
    {
        if ($validator->errors()->has('supporting_document') || $this->hasFile('supporting_document')) {
            return;
        }

        $borrowing = $this->route('borrowing');
        $removing = $this->boolean('remove_supporting_document');
        $kept = $borrowing instanceof Borrowing
            && ! $removing
            && app(BorrowingSupportingDocumentService::class)->exists($borrowing);

        if (! $kept) {
            $validator->errors()->add('supporting_document', 'Dokumen pendukung wajib diunggah.');
        }
    }
}
