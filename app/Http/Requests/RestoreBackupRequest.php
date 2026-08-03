<?php

namespace App\Http\Requests;

use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('backup.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'backup_file' => ['nullable', 'string', 'max:255'],
            'backup_upload' => ['nullable', 'file', 'max:102400'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasSelect = filled($this->input('backup_file'));
            $hasUpload = $this->hasFile('backup_upload');

            if (! $hasSelect && ! $hasUpload) {
                $validator->errors()->add('backup_file', 'Pilih file backup dari daftar atau unggah file .sqlite.');
            }

            if ($hasSelect && $hasUpload) {
                $validator->errors()->add('backup_file', 'Gunakan salah satu: pilih dari daftar atau unggah file, jangan keduanya.');
            }

            if ($hasSelect) {
                $name = basename((string) $this->input('backup_file'));
                if (preg_match(DatabaseBackupService::FILENAME_PATTERN, $name) !== 1) {
                    $validator->errors()->add('backup_file', 'Nama file backup tidak valid.');
                }
            }

            if ($hasUpload) {
                $file = $this->file('backup_upload');
                $ext = strtolower((string) $file?->getClientOriginalExtension());
                if (! in_array($ext, ['sqlite', 'db'], true)) {
                    $validator->errors()->add('backup_upload', 'File backup harus berekstensi .sqlite atau .db.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'backup_file' => 'File backup',
            'backup_upload' => 'Unggahan backup',
        ];
    }
}
