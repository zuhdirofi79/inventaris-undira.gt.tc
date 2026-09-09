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
            'decisions.required' => 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak terlebih dahulu.',
            'decisions.min' => 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak terlebih dahulu.',
            'decisions.*.action.in' => 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak terlebih dahulu.',
            'decisions.*.action.required' => 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak terlebih dahulu.',
        ];
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
