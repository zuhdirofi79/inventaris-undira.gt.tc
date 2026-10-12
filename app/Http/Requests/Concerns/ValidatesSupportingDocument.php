<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

trait ValidatesSupportingDocument
{
    public const SUPPORTING_DOCUMENT_MAX_KILOBYTES = 5120;

    /**
     * @return list<string>
     */
    protected function supportingDocumentRules(): array
    {
        return ['nullable', 'file', 'mimes:pdf,jpg,jpeg', 'max:'.self::SUPPORTING_DOCUMENT_MAX_KILOBYTES];
    }

    /**
     * @return array<string, string>
     */
    protected function supportingDocumentMessages(): array
    {
        return [
            'supporting_document.mimes' => 'Format dokumen harus PDF, JPG, atau JPEG.',
            'supporting_document.max' => 'Ukuran dokumen maksimal 5 MB.',
            'supporting_document.file' => 'Dokumen pendukung tidak valid.',
            'supporting_document.uploaded' => 'Dokumen gagal diunggah. Silakan coba kembali.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supporting_document' => 'dokumen pendukung',
        ];
    }

    protected function guardSupportingDocument(Validator $validator): void
    {
        $file = $this->file('supporting_document');

        if (! $file instanceof UploadedFile) {
            return;
        }

        if (! $file->isValid()) {
            $validator->errors()->forget('supporting_document');
            $validator->errors()->add('supporting_document', $this->supportingDocumentUploadMessage($file));

            Log::warning('Dokumen pendukung ditolak sebelum disimpan.', [
                'php_upload_error' => $file->getError(),
            ]);

            return;
        }

        if ($validator->errors()->has('supporting_document')) {
            return;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg'], true)) {
            $validator->errors()->add('supporting_document', 'Format dokumen harus PDF, JPG, atau JPEG.');
        }
    }

    protected function supportingDocumentUploadMessage(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran dokumen maksimal 5 MB.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Dokumen tidak dapat disimpan saat ini. Silakan coba kembali.',
            default => 'Dokumen gagal diunggah. Silakan coba kembali.',
        };
    }
}
