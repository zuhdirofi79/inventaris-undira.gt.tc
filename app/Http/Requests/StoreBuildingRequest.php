<?php

namespace App\Http\Requests;

use App\Support\Validation\ActiveExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('location.manage') ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('building') !== null;

        return [
            'campus_id' => [$isUpdate ? 'sometimes' : 'required', ActiveExists::rule('campuses')],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
