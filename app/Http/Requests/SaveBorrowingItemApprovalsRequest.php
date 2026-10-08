<?php

namespace App\Http\Requests;

use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;

class SaveBorrowingItemApprovalsRequest extends FormRequest
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
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.id' => ['required', 'integer', 'min:1'],
            'decisions.*.action' => ['required', 'in:approve,reject'],
            'decisions.*.rejection_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'decisions.required' => 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak untuk seluruh inventaris.',
            'decisions.min' => 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak untuk seluruh inventaris.',
            'decisions.*.action.in' => 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak untuk seluruh inventaris.',
            'decisions.*.action.required' => 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak untuk seluruh inventaris.',
            'decisions.*.rejection_reason.required' => 'Alasan penolakan wajib diisi.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('decisions', []) as $index => $row) {
                if (! is_array($row) || ($row['action'] ?? null) !== 'reject') {
                    continue;
                }

                if (trim((string) ($row['rejection_reason'] ?? '')) === '') {
                    $validator->errors()->add(
                        "decisions.$index.rejection_reason",
                        'Alasan penolakan wajib diisi.',
                    );
                }
            }
        });
    }

    /**
     * @return list<array{id: int, action: string, rejection_reason: string}>
     */
    public function decisionsPayload(): array
    {
        $rows = [];

        foreach ($this->validated('decisions') as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'action' => (string) $row['action'],
                'rejection_reason' => trim((string) ($row['rejection_reason'] ?? '')),
            ];
        }

        return $rows;
    }
}
