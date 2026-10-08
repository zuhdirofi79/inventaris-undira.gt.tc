<?php

namespace App\Http\Requests;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'borrower_phone' => ['required', 'string', 'max:50', 'regex:/^(?:\+62|62|0)[\s.\-]*8[1-9](?:[\s.\-]*[0-9]){6,11}$/'],
            'borrow_date' => ['sometimes', 'required', 'date'],
            'return_date' => ['nullable', 'date'],
            'expected_return_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', Rule::enum(BorrowingStatus::class)],
            'notes' => ['required', 'string'],
            'supporting_document' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg',
                'max:5120',
            ],
            'remove_supporting_document' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('supporting_document');
            if ($file && ! $validator->errors()->has('supporting_document')) {
                $ext = strtolower((string) $file->getClientOriginalExtension());
                if (! in_array($ext, ['pdf', 'jpg', 'jpeg'], true)) {
                    $validator->errors()->add('supporting_document', 'Format file harus PDF, JPG, atau JPEG.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'borrower_phone.required' => 'Nomor Telepon / No. HP wajib diisi.',
            'borrower_phone.regex' => 'Nomor Telepon / No. HP tidak valid. Gunakan format 08xxxxxxxxxx, 628xxxxxxxxxx, atau +628xxxxxxxxxx.',
            'notes.required' => 'Keperluan wajib diisi.',
            'supporting_document.mimes' => 'Format file harus PDF, JPG, atau JPEG.',
            'supporting_document.max' => 'Ukuran file maksimal 5 MB.',
            'supporting_document.file' => 'Dokumen pendukung tidak valid.',
        ];
    }
}
