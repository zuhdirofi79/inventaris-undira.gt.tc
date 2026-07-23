<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow extended audit actions + flexible attachment document types without
        // rewriting historical rows (SQLite/MySQL-friendly string columns).
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('action', 40)->change();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE inventory_attachments MODIFY type VARCHAR(32) NOT NULL");
        } else {
            Schema::table('inventory_attachments', function (Blueprint $table) {
                $table->string('type', 32)->change();
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: keep string columns.
    }
};
