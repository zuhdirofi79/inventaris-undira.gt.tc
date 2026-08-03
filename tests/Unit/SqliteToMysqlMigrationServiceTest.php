<?php

namespace Tests\Unit;

use App\Services\SqliteToMysqlMigrationService;
use RuntimeException;
use Tests\TestCase;

class SqliteToMysqlMigrationServiceTest extends TestCase
{
    public function test_tables_are_in_dependency_safe_order(): void
    {
        $tables = SqliteToMysqlMigrationService::TABLES;

        $this->assertSame('users', $tables[0]);
        $this->assertTrue(array_search('campuses', $tables, true) < array_search('buildings', $tables, true));
        $this->assertTrue(array_search('buildings', $tables, true) < array_search('floors', $tables, true));
        $this->assertTrue(array_search('floors', $tables, true) < array_search('rooms', $tables, true));
        $this->assertTrue(array_search('categories', $tables, true) < array_search('sub_categories', $tables, true));
        $this->assertTrue(array_search('rooms', $tables, true) < array_search('inventory_items', $tables, true));
        $this->assertTrue(array_search('inventory_items', $tables, true) < array_search('borrowings', $tables, true));
        $this->assertTrue(array_search('borrowings', $tables, true) < array_search('borrowing_items', $tables, true));
        $this->assertTrue(array_search('inventory_items', $tables, true) < array_search('maintenances', $tables, true));
        $this->assertTrue(array_search('maintenances', $tables, true) < array_search('maintenance_photos', $tables, true));
        $this->assertContains('audit_logs', $tables);
        $this->assertNotContains('migrations', $tables);
        $this->assertNotContains('cache', $tables);
        $this->assertNotContains('jobs', $tables);
    }

    public function test_missing_sqlite_file_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File SQLite tidak ditemukan');

        app(SqliteToMysqlMigrationService::class)->configureSource(storage_path('no-such-db.sqlite'));
    }
}
