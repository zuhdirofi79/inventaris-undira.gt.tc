<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\UnableToRetrieveMetadata;
use Symfony\Component\HttpFoundation\Response;

final class PrivateDocumentResponse
{
    /**
     * @return array{0: string, 1: string}|null
     */
    public static function safePath(string $path, string $directoryPrefix): ?array
    {
        $normalized = str_replace('\\', '/', $path);
        $prefix = trim(str_replace('\\', '/', $directoryPrefix), '/').'/';

        if ($normalized === ''
            || str_contains($normalized, "\0")
            || str_contains($normalized, '..')
            || str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:/', $normalized) === 1
            || ! str_starts_with($normalized, $prefix)
        ) {
            return null;
        }

        return [$normalized, strtolower(pathinfo($normalized, PATHINFO_EXTENSION))];
    }

    /**
     * @return array{state: string, name: string, size: string, isImage: bool}
     */
    public static function present(
        ?string $path,
        ?string $name,
        ?int $size,
        string $directoryPrefix,
        string $disk = 'local',
    ): array {
        $empty = ['state' => 'empty', 'name' => '', 'size' => '', 'isImage' => false];

        if (! is_string($path) || $path === '') {
            return $empty;
        }

        $safe = self::safePath($path, $directoryPrefix);
        if ($safe === null || ! Storage::disk($disk)->exists($safe[0])) {
            return ['state' => 'missing', 'name' => '', 'size' => '', 'isImage' => false];
        }

        $label = is_string($name) && trim($name) !== '' ? $name : basename($safe[0]);

        return [
            'state' => 'ready',
            'name' => $label,
            'size' => self::humanSize($size),
            'isImage' => in_array($safe[1], ['jpg', 'jpeg'], true),
        ];
    }

    public static function stream(
        string $disk,
        string $path,
        string $directoryPrefix,
        string $downloadName,
        bool $download,
        string $logContext,
    ): Response {
        $safe = self::safePath($path, $directoryPrefix);

        if ($safe === null || ! Storage::disk($disk)->exists($safe[0])) {
            Log::warning('Dokumen pendukung tidak dapat dibaca.', ['context' => $logContext]);
            abort(404, 'Dokumen pendukung tidak ditemukan.');
        }

        [$stored, $extension] = $safe;
        $name = self::downloadName($downloadName, $extension);

        if (! $download && ! in_array($extension, ['pdf', 'jpg', 'jpeg'], true)) {
            return response(
                'Format dokumen ini tidak dapat ditampilkan langsung di browser. Gunakan tombol Download.',
                200,
                [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'X-Content-Type-Options' => 'nosniff',
                    'Content-Disposition' => 'inline',
                ],
            );
        }

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        try {
            return Storage::disk($disk)->response(
                $stored,
                $name,
                [
                    'Content-Type' => $mime,
                    'X-Content-Type-Options' => 'nosniff',
                ],
                $download ? 'attachment' : 'inline',
            );
        } catch (UnableToRetrieveMetadata|InvalidArgumentException) {
            Log::warning('Dokumen pendukung tidak dapat dibaca.', ['context' => $logContext]);
            abort(404, 'Dokumen pendukung tidak ditemukan.');
        }
    }

    public static function humanSize(?int $bytes): string
    {
        if ($bytes === null || $bytes < 0) {
            return '';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }

    private static function downloadName(string $name, string $extension): string
    {
        $base = basename(str_replace(["\0", '\\'], '/', $name));
        $base = str_replace(["\r", "\n", '"'], '', $base);
        $base = trim($base);

        if ($base === '' || $base === '.' || $base === '..') {
            $suffix = in_array($extension, ['pdf', 'jpg', 'jpeg'], true) ? $extension : 'bin';

            return 'dokumen-pendukung.'.$suffix;
        }

        return mb_substr($base, 0, 180);
    }
}
