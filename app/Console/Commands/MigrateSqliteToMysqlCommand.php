<?php

namespace App\Console\Commands;

use App\Services\SqliteToMysqlMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MigrateSqliteToMysqlCommand extends Command
{
    protected $signature = 'inventory:migrate-sqlite-to-mysql
                            {--sqlite-path= : Path file SQLite sumber (default: database/database.sqlite)}
                            {--host= : Host MySQL target}
                            {--port=3306 : Port MySQL}
                            {--database= : Nama database MySQL}
                            {--username= : Username MySQL}
                            {--password= : Password MySQL}
                            {--socket= : Unix socket MySQL (opsional)}
                            {--dry-run : Hanya hitung & validasi koneksi, tanpa menulis data}
                            {--force : Lewati konfirmasi interaktif}';

    protected $description = 'Migrasi data aplikasi dari SQLite ke MySQL/MariaDB dengan mempertahankan ID dan timestamp';

    public function handle(SqliteToMysqlMigrationService $migrator): int
    {
        $sqlitePath = (string) ($this->option('sqlite-path') ?: database_path('database.sqlite'));
        $dryRun = (bool) $this->option('dry-run');

        $mysql = [
            'host' => (string) ($this->option('host') ?: env('MYSQL_MIGRATE_HOST', env('DB_HOST', '127.0.0.1'))),
            'port' => (string) ($this->option('port') ?: env('MYSQL_MIGRATE_PORT', env('DB_PORT', '3306'))),
            'database' => (string) ($this->option('database') ?: env('MYSQL_MIGRATE_DATABASE', '')),
            'username' => (string) ($this->option('username') ?: env('MYSQL_MIGRATE_USERNAME', env('DB_USERNAME', ''))),
            'password' => (string) ($this->option('password') ?? env('MYSQL_MIGRATE_PASSWORD', env('DB_PASSWORD', ''))),
            'unix_socket' => (string) ($this->option('socket') ?: env('MYSQL_MIGRATE_SOCKET', '')),
        ];

        // If target DB name omitted, only reuse DB_DATABASE when app is already on mysql.
        if ($mysql['database'] === '' && env('DB_CONNECTION') === 'mysql') {
            $mysql['database'] = (string) env('DB_DATABASE', '');
        }

        $this->info('Inventaris UNDIRA — SQLite → MySQL data migration');
        $this->line('Source SQLite : '.$sqlitePath);
        $this->line('Target MySQL  : '.$mysql['username'].'@'.$mysql['host'].':'.$mysql['port'].'/'.$mysql['database']);
        $this->line('Mode          : '.($dryRun ? 'DRY-RUN' : 'EXECUTE'));
        $this->newLine();

        try {
            $migrator->configureSource($sqlitePath);
            $migrator->configureTarget($mysql);
            $migrator->assertConnections();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $tables = $migrator->migratableTables();
        if ($tables === []) {
            $this->error('Tidak ada tabel yang bisa dimigrasikan. Pastikan MySQL sudah di-migrate (php artisan migrate --database=...).');

            return self::FAILURE;
        }

        $this->table(['#', 'Table'], collect($tables)->values()->map(fn ($t, $i) => [$i + 1, $t]));

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('Lanjutkan migrasi data ke MySQL? Primary key akan dipertahankan; baris yang sudah ada akan dilewati.', false)) {
                $this->warn('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        $started = microtime(true);
        $bar = null;

        try {
            $runner = function () use ($migrator, $dryRun, &$bar) {
                return $migrator->migrate(
                    function (string $table, int $total) use (&$bar): void {
                        if ($bar !== null) {
                            $bar->finish();
                            $this->newLine();
                        }
                        $this->info("Migrating {$table}...");
                        Log::info("[sqlite→mysql] Migrating {$table} ({$total} rows)");
                        $bar = $this->output->createProgressBar(max($total, 1));
                        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
                        $bar->start();
                        if ($total === 0) {
                            $bar->advance();
                        }
                    },
                    function () use (&$bar): void {
                        $bar?->advance();
                    },
                    $dryRun
                );
            };

            $result = $dryRun
                ? $runner()
                : DB::connection(SqliteToMysqlMigrationService::TARGET)->transaction(function () use ($runner) {
                    DB::connection(SqliteToMysqlMigrationService::TARGET)->statement('SET FOREIGN_KEY_CHECKS=0');
                    try {
                        return $runner();
                    } finally {
                        DB::connection(SqliteToMysqlMigrationService::TARGET)->statement('SET FOREIGN_KEY_CHECKS=1');
                    }
                });
        } catch (Throwable $e) {
            $bar?->finish();
            $this->newLine(2);
            $this->error('Migrasi gagal — seluruh transaksi di-rollback.');
            $this->error($e->getMessage());
            Log::error('[sqlite→mysql] '.$e->getMessage(), ['exception' => $e]);

            return self::FAILURE;
        }

        $bar?->finish();
        $this->newLine(2);

        foreach ($result['migrated'] as $table => $count) {
            $skip = $result['skipped'][$table] ?? 0;
            $suffix = $skip > 0 ? " (skipped existing: {$skip})" : '';
            $this->line("Success {$table}: {$count} rows{$suffix}");
            Log::info("[sqlite→mysql] Success {$table}: {$count} rows{$suffix}");
        }

        if ($result['missing_source'] !== []) {
            $this->warn('Lewati (tidak ada di SQLite): '.implode(', ', $result['missing_source']));
        }
        if ($result['missing_target'] !== []) {
            $this->warn('Lewati (tidak ada di MySQL — jalankan migrate dulu): '.implode(', ', $result['missing_target']));
        }

        $this->newLine();
        $this->info('Validasi jumlah record (SQLite vs MySQL):');
        $comparison = $migrator->compareCounts();
        $this->table(
            ['Table', 'SQLite', 'MySQL', 'Match'],
            collect($comparison)->map(fn ($row) => [
                $row['table'],
                number_format($row['sqlite']),
                number_format($row['mysql']),
                $row['match'] ? 'OK' : 'MISMATCH',
            ])
        );

        $mismatches = collect($comparison)->where('match', false)->pluck('table')->all();
        $elapsed = round(microtime(true) - $started, 2);

        if ($mismatches !== [] && ! $dryRun) {
            $this->warn('Beberapa tabel belum identik (mungkin ada data MySQL pra-ada yang berbeda). Cek: '.implode(', ', $mismatches));
        } elseif (! $dryRun) {
            $this->info('Semua tabel yang dibandingkan jumlahnya identik.');
        }

        $this->newLine();
        $this->info($dryRun ? "Dry-run finished in {$elapsed}s." : "Finished in {$elapsed}s.");

        if (! $dryRun) {
            $this->comment('Langkah berikutnya: set DB_CONNECTION=mysql di .env Hostinger, lalu uji login & inventaris.');
        }

        return self::SUCCESS;
    }
}
