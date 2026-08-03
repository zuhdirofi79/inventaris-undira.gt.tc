<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseBackupService
{
    public const DISK = 'local';

    public const DIRECTORY = 'backups';

    public const FILENAME_PATTERN = '/^backup_[\w\-]+\.sqlite$/i';

    public function driver(): string
    {
        $connection = (string) config('database.default');

        return (string) config("database.connections.{$connection}.driver");
    }

    public function isSqlite(): bool
    {
        return $this->driver() === 'sqlite';
    }

    public function databasePath(): string
    {
        $connection = (string) config('database.default');
        $path = (string) config("database.connections.{$connection}.database");

        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('File database SQLite tidak ditemukan.');
        }

        return $path;
    }

    /**
     * @return list<array{name: string, size: int, modified: int}>
     */
    public function listBackups(): array
    {
        $disk = Storage::disk(self::DISK);

        return collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $path) => (bool) preg_match(self::FILENAME_PATTERN, basename($path)))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => (int) $disk->size($path),
                'modified' => (int) $disk->lastModified($path),
            ])
            ->sortByDesc('modified')
            ->values()
            ->all();
    }

    public function createBackup(): string
    {
        $this->assertSqlite();

        $filename = 'backup_'.now()->format('Ymd_His').'.sqlite';
        $relative = self::DIRECTORY.'/'.$filename;
        $contents = file_get_contents($this->databasePath());

        if ($contents === false || $contents === '') {
            throw new RuntimeException('Gagal membaca database untuk backup.');
        }

        if (! $this->looksLikeSqlite($contents)) {
            throw new RuntimeException('File database sumber bukan SQLite yang valid.');
        }

        Storage::disk(self::DISK)->put($relative, $contents);

        return $filename;
    }

    public function absolutePath(string $filename): string
    {
        $safe = $this->assertSafeFilename($filename);
        $relative = self::DIRECTORY.'/'.$safe;

        if (! Storage::disk(self::DISK)->exists($relative)) {
            throw new RuntimeException('File backup tidak ditemukan.');
        }

        return Storage::disk(self::DISK)->path($relative);
    }

    public function restoreFromStoredBackup(string $filename): void
    {
        $this->assertSqlite();
        $source = $this->absolutePath($filename);

        if (! $this->isValidSqliteFile($source)) {
            throw new RuntimeException('File backup tidak valid (bukan SQLite).');
        }

        $this->replaceDatabase($source);
    }

    public function restoreFromUpload(UploadedFile $file): string
    {
        $this->assertSqlite();

        $original = (string) $file->getClientOriginalName();
        $safeName = 'backup_upload_'.now()->format('Ymd_His').'.sqlite';

        if (preg_match(self::FILENAME_PATTERN, basename($original)) === 1) {
            $safeName = basename($original);
        }

        $tmpPath = $file->getRealPath();
        if ($tmpPath === false || ! is_file($tmpPath)) {
            throw new RuntimeException('Unggahan backup tidak dapat dibaca.');
        }

        if (! $this->isValidSqliteFile($tmpPath)) {
            throw new RuntimeException('File yang diunggah bukan backup SQLite yang valid.');
        }

        $relative = self::DIRECTORY.'/'.$safeName;
        Storage::disk(self::DISK)->put($relative, file_get_contents($tmpPath) ?: '');

        $this->replaceDatabase(Storage::disk(self::DISK)->path($relative));

        return $safeName;
    }

    public function assertSafeFilename(string $filename): string
    {
        $safe = basename($filename);
        if (preg_match(self::FILENAME_PATTERN, $safe) !== 1) {
            throw new RuntimeException('Nama file backup tidak diizinkan.');
        }

        return $safe;
    }

    public function isValidSqliteFile(string $absolutePath): bool
    {
        if (! is_file($absolutePath) || filesize($absolutePath) < 16) {
            return false;
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        return is_string($header) && $this->looksLikeSqlite($header);
    }

    protected function looksLikeSqlite(string $contents): bool
    {
        return str_starts_with($contents, 'SQLite format 3');
    }

    protected function replaceDatabase(string $sourceAbsolute): void
    {
        $target = $this->databasePath();
        DB::disconnect();

        if (! @copy($sourceAbsolute, $target)) {
            throw new RuntimeException('Gagal menulis file database saat restore.');
        }
    }

    protected function assertSqlite(): void
    {
        if (! $this->isSqlite()) {
            throw new RuntimeException('Backup/restore otomatis hanya tersedia untuk database SQLite.');
        }
    }
}
