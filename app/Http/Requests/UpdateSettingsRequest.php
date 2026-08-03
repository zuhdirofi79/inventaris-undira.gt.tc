<?php

namespace App\Http\Requests;

use App\Support\AppSettings;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:120'],
            'university_name' => ['required', 'string', 'max:160'],
            'footer_text' => ['required', 'string', 'max:255'],
            'sequence_padding' => ['required', 'integer', 'min:1', 'max:12'],
            'scan_base_url' => ['nullable', 'string', 'max:255', 'url'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,ico,webp', 'max:512'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'app_name' => 'Nama aplikasi',
            'university_name' => 'Nama universitas',
            'footer_text' => 'Footer',
            'sequence_padding' => 'Sequence padding',
            'scan_base_url' => 'Scan base URL',
            'logo' => 'Logo',
            'favicon' => 'Favicon',
        ];
    }

    public function messages(): array
    {
        return [
            'scan_base_url.url' => 'Scan Base URL harus berupa URL yang valid (contoh: https://inventaris.undira.ac.id).',
            'sequence_padding.min' => 'Sequence padding minimal 1 digit.',
            'sequence_padding.max' => 'Sequence padding maksimal 12 digit.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $scan = $this->input('scan_base_url');
        if (is_string($scan) && trim($scan) === '') {
            $this->merge(['scan_base_url' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function settingValues(): array
    {
        return [
            AppSettings::KEY_APP_NAME => trim((string) $this->input('app_name')),
            AppSettings::KEY_UNIVERSITY_NAME => trim((string) $this->input('university_name')),
            AppSettings::KEY_FOOTER_TEXT => trim((string) $this->input('footer_text')),
            AppSettings::KEY_SEQUENCE_PADDING => (string) (int) $this->input('sequence_padding'),
            AppSettings::KEY_SCAN_BASE_URL => $this->filled('scan_base_url')
                ? rtrim(trim((string) $this->input('scan_base_url')), '/')
                : '',
        ];
    }
}
