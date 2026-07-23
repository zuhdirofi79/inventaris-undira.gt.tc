<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\DeletionApproval;
use App\Models\Floor;
use App\Models\InventoryAttachment;
use App\Models\InventoryItem;
use App\Models\InventoryMasterConfiguration;
use App\Models\InventoryMutation;
use App\Models\InventoryPhoto;
use App\Models\InventorySequence;
use App\Models\Maintenance;
use App\Models\Room;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeInventoryDataCommand extends Command
{
    protected $signature = 'inventory:purge
                            {--dry-run : Preview deletions without changing data}
                            {--execute : Perform the purge after confirmation}
                            {--reset-sequences : Reset inventory_sequences.last_value to 0}
                            {--confirm= : Confirmation phrase (required with --execute)}';

    protected $description = 'Purge inventory domain data from a local/dev database while preserving master data';

    private const CONFIRM_PHRASE = 'PURGE INVENTORY DEVELOPMENT DATA';

    private const ALLOWED_ENVS = ['local', 'development', 'testing'];

    /** @var list<string> */
    private const PURGE_TABLES = [
        'stock_opname_items',
        'stock_opnames',
        'borrowings',
        'maintenance_schedules',
        'maintenances',
        'deletion_approvals',
        'inventory_mutations',
        'inventory_photos',
        'inventory_attachments',
        'inventory_items',
        'inventory_master_configurations',
    ];

    public function handle(AuditLogService $auditLogService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $execute = (bool) $this->option('execute');
        $resetSequences = (bool) $this->option('reset-sequences');

        if (! $dryRun && ! $execute) {
            $this->error('Specify --dry-run or --execute.');

            return self::FAILURE;
        }

        if ($dryRun && $execute) {
            $this->error('Use either --dry-run or --execute, not both.');

            return self::FAILURE;
        }

        if (! $this->assertSafeEnvironment()) {
            return self::FAILURE;
        }

        $counts = $this->collectCounts();
        $this->renderPlan($counts, $resetSequences);

        if ($dryRun) {
            $this->info('Dry-run complete. No data was deleted.');

            return self::SUCCESS;
        }

        if (! $this->assertConfirmation()) {
            return self::FAILURE;
        }

        $backup = $this->createBackup($counts);
        if ($backup === null) {
            $this->error('Backup failed or could not be verified. Purge aborted.');

            return self::FAILURE;
        }

        $this->info('Backup verified: '.$backup['manifest_path']);

        try {
            $deleted = DB::transaction(function () use ($resetSequences) {
                return $this->performPurge($resetSequences);
            });
        } catch (Throwable $e) {
            Log::error('inventory:purge failed', ['message' => $e->getMessage()]);
            $this->error('Purge failed and was rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        // Remove generated files only after DB commit. Never touch real disks in PHPUnit.
        $filesDeleted = $this->purgeGeneratedFiles();

        $auditLogService->log(
            null,
            AuditAction::Delete,
            null,
            null,
            [
                'environment' => config('app.env'),
                'database' => $this->databaseLabel(),
                'deleted' => $deleted,
                'generated_files_deleted' => $filesDeleted,
                'sequences_reset' => $resetSequences,
                'backup' => [
                    'directory' => $backup['directory'],
                    'manifest_path' => $backup['manifest_path'],
                ],
                'purge_action' => 'INVENTORY_DATA_PURGE',
                'reason' => 'Reinitialisasi data inventaris dan konsolidasi input melalui Konfigurasi Inventaris.',
            ],
            'INVENTORY_DATA_PURGE',
            'Reinitialisasi data inventaris dan konsolidasi input melalui Konfigurasi Inventaris.',
            (int) ($deleted['inventory_items'] ?? 0),
        );

        $this->newLine();
        $this->info('Purge completed successfully.');
        $this->table(['Table', 'Deleted'], collect($deleted)->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->line('Generated files deleted: '.$filesDeleted);
        $this->line('Sequences reset: '.($resetSequences ? 'yes' : 'no'));
        $this->line('Backup: '.$backup['directory']);

        return self::SUCCESS;
    }

    private function assertSafeEnvironment(): bool
    {
        $cwd = realpath(base_path()) ?: base_path();
        $expected = realpath('C:\\projects\\inventaris-undira.gt.tc')
            ?: 'C:\\projects\\inventaris-undira.gt.tc';

        $env = (string) config('app.env');
        $connection = (string) config('database.default');
        $database = $this->databaseLabel();

        $this->line('Project: '.$cwd);
        $this->line('APP_ENV: '.$env);
        $this->line('APP_DEBUG: '.(config('app.debug') ? 'true' : 'false'));
        $this->line('DB_CONNECTION: '.$connection);
        $this->line('DB_DATABASE: '.$database);

        if (strcasecmp(str_replace('\\', '/', $cwd), str_replace('\\', '/', $expected)) !== 0
            && ! str_ends_with(str_replace('\\', '/', $cwd), 'inventaris-undira.gt.tc')) {
            // Allow CI/temp copies that still end with the project folder name.
            if (! str_contains(str_replace('\\', '/', $cwd), 'inventaris-undira.gt.tc')) {
                $this->error('Refusing purge: unexpected project path.');

                return false;
            }
        }

        if (! in_array($env, self::ALLOWED_ENVS, true)) {
            $this->error("Refusing purge: APP_ENV [{$env}] is not local/development/testing.");

            return false;
        }

        if ($connection === 'sqlite') {
            $path = (string) config('database.connections.sqlite.database');
            if ($path !== ':memory:' && ! str_contains(str_replace('\\', '/', $path), 'inventaris-undira.gt.tc')
                && ! str_starts_with($path, database_path())) {
                // Relative database.sqlite under this project is OK.
                $resolved = database_path(basename($path) === $path ? $path : basename($path));
                if (! File::exists(database_path('database.sqlite')) && ! File::exists($path)) {
                    $this->error('Refusing purge: SQLite database file not found in project.');

                    return false;
                }
                unset($resolved);
            }
        } else {
            $host = (string) (config("database.connections.{$connection}.host") ?? '');
            if ($host !== '' && ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
                $this->error("Refusing purge: remote DB host [{$host}] looks non-local.");

                return false;
            }
        }

        return true;
    }

    private function assertConfirmation(): bool
    {
        $provided = (string) ($this->option('confirm') ?? '');
        if ($provided === '') {
            $provided = (string) $this->ask('Type the confirmation phrase to continue');
        }

        if ($provided !== self::CONFIRM_PHRASE) {
            $this->error('Confirmation phrase mismatch. Purge aborted.');

            return false;
        }

        return true;
    }

    /**
     * @return array<string, int>
     */
    private function collectCounts(): array
    {
        $counts = [];
        foreach (self::PURGE_TABLES as $table) {
            $counts[$table] = Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
        }
        $counts['inventory_items_trashed'] = Schema::hasTable('inventory_items')
            ? (int) InventoryItem::onlyTrashed()->count()
            : 0;
        $counts['inventory_sequences'] = Schema::hasTable('inventory_sequences')
            ? (int) DB::table('inventory_sequences')->count()
            : 0;
        $counts['inventory_audit_logs'] = Schema::hasTable('audit_logs')
            ? (int) AuditLog::query()->where('auditable_type', InventoryItem::class)->count()
            : 0;

        $counts['preserve_users'] = User::query()->count();
        $counts['preserve_campuses'] = Campus::query()->count();
        $counts['preserve_buildings'] = Building::query()->count();
        $counts['preserve_floors'] = Floor::query()->count();
        $counts['preserve_rooms'] = Room::query()->count();
        $counts['preserve_categories'] = Category::query()->count();

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function renderPlan(array $counts, bool $resetSequences): void
    {
        $this->newLine();
        $this->info('Inventory dependency purge plan');
        $this->table(
            ['Target', 'Count', 'Action'],
            [
                ['stock_opname_items', $counts['stock_opname_items'], 'DELETE'],
                ['stock_opnames', $counts['stock_opnames'], 'DELETE'],
                ['borrowings', $counts['borrowings'], 'DELETE'],
                ['maintenances', $counts['maintenances'], 'DELETE'],
                ['deletion_approvals', $counts['deletion_approvals'], 'DELETE'],
                ['inventory_mutations', $counts['inventory_mutations'], 'DELETE'],
                ['inventory_photos', $counts['inventory_photos'], 'DELETE'],
                ['inventory_attachments', $counts['inventory_attachments'], 'DELETE'],
                ['inventory_items (+trashed '.$counts['inventory_items_trashed'].')', $counts['inventory_items'], 'FORCE DELETE'],
                ['inventory_master_configurations', $counts['inventory_master_configurations'], 'DELETE'],
                ['inventory_audit_logs', $counts['inventory_audit_logs'], 'DELETE (item-scoped)'],
                ['inventory_sequences', $counts['inventory_sequences'], $resetSequences ? 'RESET last_value=0' : 'PRESERVE'],
                ['storage inventory/qrcodes|barcodes', 'files', 'DELETE'],
            ]
        );

        $this->info('Master data preserved');
        $this->table(
            ['Master', 'Count'],
            [
                ['users', $counts['preserve_users']],
                ['campuses', $counts['preserve_campuses']],
                ['buildings', $counts['preserve_buildings']],
                ['floors', $counts['preserve_floors']],
                ['rooms', $counts['preserve_rooms']],
                ['categories', $counts['preserve_categories']],
            ]
        );
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{directory: string, sqlite_copy: ?string, json_export: string, manifest_path: string}|null
     */
    private function createBackup(array $counts): ?array
    {
        $stamp = now()->format('Ymd_His');
        $dir = storage_path('app/backups/inventory/'.$stamp);
        File::ensureDirectoryExists($dir);

        $sqliteCopy = null;
        if (config('database.default') === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath === ':memory:') {
                $this->warn('In-memory SQLite: skipping file copy; JSON export only.');
            } else {
                $source = $dbPath;
                if (! File::exists($source)) {
                    $source = database_path('database.sqlite');
                }
                if (! File::exists($source)) {
                    $this->error('SQLite source file missing.');

                    return null;
                }
                $sqliteCopy = $dir.DIRECTORY_SEPARATOR.'database.sqlite.copy';
                File::copy($source, $sqliteCopy);
                if (! File::exists($sqliteCopy) || filesize($sqliteCopy) < 1) {
                    return null;
                }
            }
        }

        $export = [];
        foreach (array_merge(self::PURGE_TABLES, ['inventory_sequences']) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $export[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        $jsonPath = $dir.DIRECTORY_SEPARATOR.'inventory_tables.json';
        File::put($jsonPath, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if (! File::exists($jsonPath) || filesize($jsonPath) < 2) {
            return null;
        }

        $commit = trim((string) @shell_exec('git rev-parse --short HEAD 2>NUL'));
        $manifest = [
            'timestamp' => now()->toIso8601String(),
            'project' => base_path(),
            'environment' => config('app.env'),
            'database' => $this->databaseLabel(),
            'connection' => config('database.default'),
            'commit' => $commit !== '' ? $commit : null,
            'counts' => $counts,
            'tables' => array_keys($export),
            'reason' => 'Reinitialisasi data inventaris dan konsolidasi input melalui Konfigurasi Inventaris.',
        ];
        $manifestPath = $dir.DIRECTORY_SEPARATOR.'manifest.json';
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'directory' => $dir,
            'sqlite_copy' => $sqliteCopy,
            'json_export' => $jsonPath,
            'manifest_path' => $manifestPath,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function performPurge(bool $resetSequences): array
    {
        $deleted = [];

        $deleted['stock_opname_items'] = StockOpnameItem::query()->count();
        StockOpnameItem::query()->delete();

        $deleted['stock_opnames'] = StockOpname::query()->count();
        StockOpname::query()->delete();

        $deleted['borrowings'] = Borrowing::withTrashed()->count();
        Borrowing::withTrashed()->forceDelete();

        if (Schema::hasTable('maintenance_schedules')) {
            $deleted['maintenance_schedules'] = DB::table('maintenance_schedules')->count();
            DB::table('maintenance_schedules')->delete();
        }

        $deleted['maintenances'] = Maintenance::query()->count();
        Maintenance::query()->delete();

        $deleted['deletion_approvals'] = DeletionApproval::query()->count();
        DeletionApproval::query()->delete();

        $deleted['inventory_mutations'] = InventoryMutation::query()->count();
        InventoryMutation::query()->delete();

        $deleted['inventory_photos'] = InventoryPhoto::query()->count();
        InventoryPhoto::query()->delete();

        $deleted['inventory_attachments'] = InventoryAttachment::query()->count();
        InventoryAttachment::query()->delete();

        $deleted['inventory_items'] = InventoryItem::withTrashed()->count();
        InventoryItem::withTrashed()->forceDelete();

        $deleted['inventory_master_configurations'] = InventoryMasterConfiguration::query()->count();
        InventoryMasterConfiguration::query()->delete();

        $deleted['inventory_audit_logs'] = AuditLog::query()
            ->where('auditable_type', InventoryItem::class)
            ->count();
        AuditLog::query()->where('auditable_type', InventoryItem::class)->delete();

        if ($resetSequences) {
            $deleted['inventory_sequences_reset'] = InventorySequence::query()->count();
            InventorySequence::query()->update(['last_value' => 0]);
            if (Schema::hasTable('inventory_global_sequences')) {
                DB::table('inventory_global_sequences')->update(['last_value' => 0]);
                $deleted['inventory_global_sequences_reset'] = 1;
            }
        } else {
            $deleted['inventory_sequences_reset'] = 0;
        }

        return $deleted;
    }

    private function purgeGeneratedFiles(): int
    {
        if (app()->runningUnitTests()) {
            return 0;
        }

        $disk = Storage::disk('public');
        $deleted = 0;

        foreach (['inventory/qrcodes', 'inventory/barcodes'] as $dir) {
            if (! $disk->exists($dir)) {
                continue;
            }
            foreach ($disk->allFiles($dir) as $file) {
                // Safety: only files under inventory/qrcodes or inventory/barcodes
                if (! str_starts_with($file, 'inventory/qrcodes/') && ! str_starts_with($file, 'inventory/barcodes/')) {
                    continue;
                }
                if ($disk->delete($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function databaseLabel(): string
    {
        $connection = (string) config('database.default');
        $db = config("database.connections.{$connection}.database");

        return is_string($db) ? $db : (string) $db;
    }
}
