<?php

namespace App\Http\Requests;

use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('location.manage') ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('room') !== null;
        /** @var \App\Models\Room|null $room */
        $room = $this->route('room');

        $floorId = $isUpdate
            ? (int) ($room?->floor_id ?? 0)
            : (int) $this->input('floor_id');

        return [
            'floor_id' => [$isUpdate ? 'sometimes' : 'required', ActiveExists::rule('floors')],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rooms', 'code')
                    ->where(fn ($q) => $q->where('floor_id', $floorId))
                    ->ignore($room?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'room_type' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode ruangan sudah digunakan pada lantai ini.',
            'code.required' => 'Kode ruangan wajib diisi.',
            'name.required' => 'Nama ruangan wajib diisi.',
            'room_type.required' => 'Jenis ruangan wajib diisi.',
            'capacity.required' => 'Kapasitas wajib diisi.',
            'capacity.min' => 'Kapasitas minimal 1.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
