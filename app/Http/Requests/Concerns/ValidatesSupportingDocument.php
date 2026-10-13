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
    protected function supportingDocumentRules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg', 'max:'.self::SUPPORTING_DOCUMENT_MAX_KILOBYTES];
    }

    /**
     * @return array<string, string>
     */
    protected function supportingDocumentMessages(): array
    {
        return [
            'supporting_document.required' => 'Dokumen pendukung wajib diunggah.',
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

            return;
        }

        if (! $this->supportingDocumentContentIsAllowed($file)) {
            $validator->errors()->add('supporting_document', 'Format dokumen harus PDF, JPG, atau JPEG.');
        }
    }

    /**
     * Reject renamed files whose bytes are not a real PDF or JPEG.
     * Reads the temp file directly so the client MIME type cannot bypass the check.
     */
    protected function supportingDocumentContentIsAllowed(UploadedFile $file): bool
    {
        $path = $file->getPathname();
        if (! is_string($path) || ! is_file($path)) {
            return false;
        }

        $head = file_get_contents($path, false, null, 0, 8);
        if (! is_string($head) || $head === '') {
            return false;
        }

        $mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($path));
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            return str_starts_with($head, '%PDF-')
                && in_array($mime, ['application/pdf', 'application/x-pdf'], true);
        }

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            return str_starts_with($head, "\xFF\xD8\xFF")
                && in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true);
        }

        return false;
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
