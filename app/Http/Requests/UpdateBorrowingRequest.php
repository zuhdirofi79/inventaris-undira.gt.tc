<?php

namespace App\Http\Requests;

use App\Enums\BorrowingStatus;
use App\Http\Requests\Concerns\ValidatesSupportingDocument;
use App\Models\Borrowing;
use App\Rules\IndonesianPhoneNumber;
use App\Services\RoomOccupancy;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateBorrowingRequest extends FormRequest
{
    use ValidatesSupportingDocument;

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
                app(RoomOccupancy::class)->assertInventoriesAvailable($ids, $start, $end);
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
            ...$this->supportingDocumentMessages(),
        ];
    }
}
