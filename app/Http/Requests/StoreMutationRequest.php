<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryMutation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', ActiveExists::rule('inventory_items')],
            'to_room_id' => ['required', ActiveExists::rule('rooms')],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.required' => 'Barang inventaris wajib dipilih.',
            'to_room_id.required' => 'Ruangan tujuan wajib dipilih.',
            'inventory_item_id.exists' => 'Barang inventaris tidak valid atau tidak aktif.',
            'to_room_id.exists' => 'Ruangan tujuan tidak valid atau tidak aktif.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $item = InventoryItem::query()->find($this->input('inventory_item_id'));
            if (! $item) {
                return;
            }

            if ((int) $item->room_id === (int) $this->input('to_room_id')) {
                $validator->errors()->add(
                    'to_room_id',
                    'Ruangan tujuan tidak boleh sama dengan ruangan saat ini.',
                );
            }
        });
    }
}
