<?php

namespace App\Http\Requests;

use App\Enums\InventoryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventorySelectorSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::enum(InventoryStatus::class)],
            'campus_id' => ['nullable', 'integer', 'min:1'],
            'building_id' => ['nullable', 'integer', 'min:1'],
            'floor_id' => ['nullable', 'integer', 'min:1'],
            'room_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'sub_category_id' => ['nullable', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function statusFilter(): ?string
    {
        $status = $this->input('status');

        return filled($status) ? (string) $status : null;
    }

    /**
     * @return array{status?:string|null,campus_id?:int,building_id?:int,floor_id?:int,room_id?:int,category_id?:int,sub_category_id?:int}
     */
    public function filters(): array
    {
        return array_filter([
            'status' => $this->statusFilter(),
            'campus_id' => $this->integer('campus_id') ?: null,
            'building_id' => $this->integer('building_id') ?: null,
            'floor_id' => $this->integer('floor_id') ?: null,
            'room_id' => $this->integer('room_id') ?: null,
            'category_id' => $this->integer('category_id') ?: null,
            'sub_category_id' => $this->integer('sub_category_id') ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
