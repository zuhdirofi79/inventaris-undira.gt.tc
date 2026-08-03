<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Copy application data from a SQLite source into a MySQL/MariaDB target.
 * Preserves primary keys, foreign keys, and timestamps. Skips existing rows.
 */
class SqliteToMysqlMigrationService
{
    public const SOURCE = 'sqlite_migrate_source';

    public const TARGET = 'mysql_migrate_target';

    /**
     * Application tables in FK-safe order (parents → children).
     * Infrastructure tables (cache/jobs/sessions/migrations) are excluded.
     *
     * @var list<string>
     */
    public const TABLES = [
        'users',
        'password_reset_tokens',
        'permissions',
        'roles',
        'role_has_permissions',
        'model_has_permissions',
        'model_has_roles',
        'campuses',
        'categories',
        'sub_categories',
        'buildings',
        'floors',
        'rooms',
        'suppliers',
        'vendors',
        'org_units',
        'procurement_types',
        'room_functions',
        'purchase_years',
        'inventory_conditions',
        'inventory_master_configurations',
        'inventory_items',
        'inventory_sequences',
        'inventory_global_sequences',
        'settings',
        'inventory_photos',
        'inventory_attachments',
        'inventory_mutations',
        'borrowings',
        'borrowing_items',
        'maintenances',
        'maintenance_photos',
        'maintenance_status_histories',
        'maintenance_schedules',
        'stock_opnames',
        'stock_opname_items',
        'deletion_approvals',
        'audit_logs',
        'notifications',
    ];

    /**
     * Composite / non-increment identity columns used for duplicate checks.
     *
     * @var array<string, list<string>>
     */
    private const IDENTITY = [
        'password_reset_tokens' => ['email'],
        'role_has_permissions' => ['permission_id', 'role_id'],
        'model_has_permissions' => ['permission_id', 'model_id', 'model_type'],
        'model_has_roles' => ['role_id', 'model_id', 'model_type'],
        'notifications' => ['id'],
    ];

    public function configureSource(string $sqlitePath): void
    {
        if (! is_file($sqlitePath)) {
            throw new RuntimeException("File SQLite tidak ditemukan: {$sqlitePath}");
        }

        config([
            'database.connections.'.self::SOURCE => [
                'driver' => 'sqlite',
                'database' => $sqlitePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge(self::SOURCE);
    }

    /**
     * @param  array{host: string, port: int|string, database: string, username: string, password?: string, unix_socket?: string}  $mysql
     */
    public function configureTarget(array $mysql): void
    {
        foreach (['host', 'database', 'username'] as $required) {
            if (empty($mysql[$required])) {
                throw new RuntimeException("Opsi MySQL --{$required} wajib diisi.");
            }
        }

        config([
            'database.connections.'.self::TARGET => [
                'driver' => 'mysql',
                'host' => $mysql['host'],
                'port' => $mysql['port'] ?? 3306,
                'database' => $mysql['database'],
                'username' => $mysql['username'],
                'password' => $mysql['password'] ?? '',
                'unix_socket' => $mysql['unix_socket'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
        ]);

        DB::purge(self::TARGET);
    }

    public function source(): Connection
    {
        return DB::connection(self::SOURCE);
    }

    public function target(): Connection
    {
        return DB::connection(self::TARGET);
    }

    public function assertConnections(): void
    {
        try {
            $this->source()->select('select 1');
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal terhubung ke SQLite sumber: '.$e->getMessage(), 0, $e);
        }

        try {
            $this->target()->select('select 1');
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal terhubung ke MySQL target: '.$e->getMessage(), 0, $e);
        }

        if ($this->source()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Koneksi sumber harus SQLite.');
        }

        if (! in_array($this->target()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Koneksi target harus MySQL/MariaDB.');
        }
    }

    /**
     * @return list<string>
     */
    public function migratableTables(): array
    {
        $tables = [];
        foreach (self::TABLES as $table) {
            if ($this->sourceHasTable($table) && $this->targetHasTable($table)) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * @param  callable(string, int): void  $onTableStart
     * @param  callable(): void  $onRowProgress
     * @return array{migrated: array<string, int>, skipped: array<string, int>, missing_source: list<string>, missing_target: list<string>}
     */
    public function migrate(callable $onTableStart, callable $onRowProgress, bool $dryRun = false): array
    {
        $migrated = [];
        $skipped = [];
        $missingSource = [];
        $missingTarget = [];

        foreach (self::TABLES as $table) {
            $hasSource = $this->sourceHasTable($table);
            $hasTarget = $this->targetHasTable($table);

            if (! $hasSource) {
                $missingSource[] = $table;

                continue;
            }
            if (! $hasTarget) {
                $missingTarget[] = $table;

                continue;
            }

            $total = (int) $this->source()->table($table)->count();
            $onTableStart($table, $total);

            if ($dryRun) {
                $migrated[$table] = $total;
                $skipped[$table] = 0;
                for ($i = 0; $i < $total; $i++) {
                    $onRowProgress();
                }

                continue;
            }

            [$copied, $skipCount] = $this->copyTable($table, $onRowProgress);
            $migrated[$table] = $copied;
            $skipped[$table] = $skipCount;
            $this->syncAutoIncrement($table);
        }

        return [
            'migrated' => $migrated,
            'skipped' => $skipped,
            'missing_source' => $missingSource,
            'missing_target' => $missingTarget,
        ];
    }

    /**
     * @return array{table: string, sqlite: int, mysql: int, match: bool}[]
     */
    public function compareCounts(): array
    {
        $rows = [];
        foreach (self::TABLES as $table) {
            if (! $this->sourceHasTable($table) || ! $this->targetHasTable($table)) {
                continue;
            }

            $sqlite = (int) $this->source()->table($table)->count();
            $mysql = (int) $this->target()->table($table)->count();
            $rows[] = [
                'table' => $table,
                'sqlite' => $sqlite,
                'mysql' => $mysql,
                'match' => $sqlite === $mysql,
            ];
        }

        return $rows;
    }

    /**
     * @param  callable(): void  $onRowProgress
     * @return array{0: int, 1: int} [copied, skipped]
     */
    protected function copyTable(string $table, callable $onRowProgress): array
    {
        $copied = 0;
        $skipped = 0;
        $identity = self::IDENTITY[$table] ?? ['id'];
        $targetColumns = $this->targetColumns($table);

        $orderColumn = in_array('id', $targetColumns, true) ? 'id' : $identity[0];

        $this->source()
            ->table($table)
            ->orderBy($orderColumn)
            ->chunk(200, function ($rows) use ($table, $identity, $targetColumns, &$copied, &$skipped, $onRowProgress) {
                foreach ($rows as $row) {
                    $payload = $this->filterColumns((array) $row, $targetColumns);
                    $keys = $this->identityValues($payload, $identity);

                    try {
                        if ($this->rowExists($table, $identity, $keys)) {
                            $skipped++;
                            $onRowProgress();

                            continue;
                        }

                        $this->target()->table($table)->insert($payload);
                        $copied++;
                        $onRowProgress();
                    } catch (Throwable $e) {
                        $idLabel = implode(', ', array_map(
                            fn ($k, $v) => "{$k}=".var_export($v, true),
                            array_keys($keys),
                            array_values($keys)
                        ));

                        throw new RuntimeException(
                            "Gagal migrasi tabel [{$table}] baris [{$idLabel}]: {$e->getMessage()}",
                            0,
                            $e
                        );
                    }
                }
            });

        return [$copied, $skipped];
    }

    /**
     * @param  list<string>  $identity
     * @param  array<string, mixed>  $keys
     */
    protected function rowExists(string $table, array $identity, array $keys): bool
    {
        $query = $this->target()->table($table);
        foreach ($identity as $column) {
            $query->where($column, $keys[$column] ?? null);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $identity
     * @return array<string, mixed>
     */
    protected function identityValues(array $payload, array $identity): array
    {
        $keys = [];
        foreach ($identity as $column) {
            if (! array_key_exists($column, $payload)) {
                throw new RuntimeException("Kolom identitas [{$column}] tidak ada pada baris sumber.");
            }
            $keys[$column] = $payload[$column];
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    protected function filterColumns(array $row, array $allowed): array
    {
        $filtered = [];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $row)) {
                $filtered[$column] = $row[$column];
            }
        }

        return $filtered;
    }

    /**
     * @return list<string>
     */
    protected function targetColumns(string $table): array
    {
        return Schema::connection(self::TARGET)->getColumnListing($table);
    }

    protected function syncAutoIncrement(string $table): void
    {
        if (isset(self::IDENTITY[$table]) && self::IDENTITY[$table] !== ['id']) {
            return;
        }

        if (! in_array('id', $this->targetColumns($table), true)) {
            return;
        }

        $max = (int) ($this->target()->table($table)->max('id') ?? 0);
        if ($max < 1) {
            return;
        }

        // Only for integer auto-increment tables (skip UUID notifications).
        if ($table === 'notifications') {
            return;
        }

        $safeTable = str_replace('`', '``', $table);
        $this->target()->statement('ALTER TABLE `'.$safeTable.'` AUTO_INCREMENT = '.($max + 1));
    }

    protected function sourceHasTable(string $table): bool
    {
        return Schema::connection(self::SOURCE)->hasTable($table);
    }

    protected function targetHasTable(string $table): bool
    {
        return Schema::connection(self::TARGET)->hasTable($table);
    }
}
