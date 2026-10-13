<?php

namespace App\Services;

use App\Models\Borrowing;
use App\Support\PrivateDocumentResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BorrowingSupportingDocumentService
{
    public const DISK = 'local';

    public const MAX_KILOBYTES = 5120;

    /**
     * Store one supporting file (PDF, JPG, or JPEG). Does not delete any previous file.
     *
     * @return array{supporting_document_path: string, supporting_document_original_name: string, supporting_document_size: int}
     */
    public function store(Borrowing $borrowing, UploadedFile $file): array
    {
        $directory = 'borrowings/'.$borrowing->id;
        $filename = Str::uuid()->toString().'.'.$this->storedExtension($file);
        $path = $file->storeAs($directory, $filename, self::DISK);

        if ($path === false || $path === '') {
            throw new \RuntimeException('Gagal menyimpan dokumen pendukung.');
        }

        $original = $this->safeOriginalName($file->getClientOriginalName());

        return [
            'supporting_document_path' => $path,
            'supporting_document_original_name' => $original,
            'supporting_document_size' => (int) $file->getSize(),
        ];
    }

    /**
     * Store a replacement PDF. Caller must delete the previous path after DB update succeeds.
     *
     * @return array{supporting_document_path: string, supporting_document_original_name: string, supporting_document_size: int}
     */
    public function replace(Borrowing $borrowing, UploadedFile $file): array
    {
        return $this->store($borrowing, $file);
    }

    public function clear(Borrowing $borrowing): void
    {
        if ($borrowing->supporting_document_path) {
            $this->deletePath($borrowing->supporting_document_path);
        }

        $borrowing->forceFill([
            'supporting_document_path' => null,
            'supporting_document_original_name' => null,
            'supporting_document_size' => null,
        ])->save();
    }

    public function deletePath(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function exists(Borrowing $borrowing): bool
    {
        $path = $borrowing->supporting_document_path;
        if (! is_string($path) || $path === '') {
            return false;
        }

        $safe = PrivateDocumentResponse::safePath($path, 'borrowings/'.$borrowing->id);

        return $safe !== null && Storage::disk(self::DISK)->exists($safe[0]);
    }

    public function stream(Borrowing $borrowing, bool $download = false): Response
    {
        return PrivateDocumentResponse::stream(
            self::DISK,
            (string) $borrowing->supporting_document_path,
            'borrowings/'.$borrowing->id,
            $borrowing->supporting_document_original_name ?: 'dokumen-pendukung.pdf',
            $download,
            'borrowing:'.$borrowing->id,
        );
    }

    public function isImage(Borrowing $borrowing): bool
    {
        return in_array($this->extension($borrowing), ['jpg', 'jpeg'], true);
    }

    public function contentType(Borrowing $borrowing): string
    {
        return $this->isImage($borrowing) ? 'image/jpeg' : 'application/pdf';
    }

    public function extension(Borrowing $borrowing): string
    {
        return $this->extensionFromPath((string) $borrowing->supporting_document_path);
    }

    public function humanSize(?int $bytes): string
    {
        if ($bytes === null || $bytes < 0) {
            return '—';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }

    protected function safeOriginalName(string $name): string
    {
        $base = basename(str_replace(["\0", '\\'], '', $name));
        $base = trim($base);

        if ($base === '') {
            return 'dokumen-pendukung.pdf';
        }

        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg'], true)) {
            $base .= '.pdf';
        }

        return Str::limit($base, 240, '');
    }

    protected function storedExtension(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['pdf', 'jpg', 'jpeg'], true)) {
            return $ext;
        }

        $guessed = strtolower((string) $file->guessExtension());

        return in_array($guessed, ['pdf', 'jpg', 'jpeg'], true) ? $guessed : 'pdf';
    }

    protected function extensionFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['pdf', 'jpg', 'jpeg'], true) ? $ext : 'pdf';
    }
}
