<?php

namespace App\Services;

use App\Models\Borrowing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BorrowingSupportingDocumentService
{
    public const DISK = 'local';

    public const MAX_KILOBYTES = 5120;

    /**
     * Store a new PDF for the borrowing. Does not delete any previous file.
     *
     * @return array{supporting_document_path: string, supporting_document_original_name: string, supporting_document_size: int}
     */
    public function store(Borrowing $borrowing, UploadedFile $file): array
    {
        $directory = 'borrowings/'.$borrowing->id;
        $filename = Str::uuid()->toString().'.pdf';
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

        return is_string($path)
            && $path !== ''
            && Storage::disk(self::DISK)->exists($path);
    }

    public function stream(Borrowing $borrowing, bool $download = false): StreamedResponse
    {
        abort_unless($this->exists($borrowing), 404);

        $path = (string) $borrowing->supporting_document_path;
        $name = $borrowing->supporting_document_original_name ?: 'dokumen-pendukung.pdf';
        $disposition = $download ? 'attachment' : 'inline';

        return Storage::disk(self::DISK)->response(
            $path,
            $name,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition.'; filename="'.$this->headerFilename($name).'"',
            ]
        );
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

        if (! str_ends_with(Str::lower($base), '.pdf')) {
            $base .= '.pdf';
        }

        return Str::limit($base, 240, '');
    }

    protected function headerFilename(string $name): string
    {
        return str_replace(['"', "\r", "\n"], '', $name);
    }
}
