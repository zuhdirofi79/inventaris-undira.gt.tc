<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional supporting PDF document for a borrowing transaction (max 1 file).
     */
    public function up(): void
    {
        if (! Schema::hasTable('borrowings')) {
            return;
        }

        Schema::table('borrowings', function (Blueprint $table) {
            if (! Schema::hasColumn('borrowings', 'supporting_document_path')) {
                $table->string('supporting_document_path')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('borrowings', 'supporting_document_original_name')) {
                $table->string('supporting_document_original_name')->nullable()->after('supporting_document_path');
            }
            if (! Schema::hasColumn('borrowings', 'supporting_document_size')) {
                $table->unsignedInteger('supporting_document_size')->nullable()->after('supporting_document_original_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('borrowings')) {
            return;
        }

        Schema::table('borrowings', function (Blueprint $table) {
            $drop = array_values(array_filter([
                Schema::hasColumn('borrowings', 'supporting_document_path') ? 'supporting_document_path' : null,
                Schema::hasColumn('borrowings', 'supporting_document_original_name') ? 'supporting_document_original_name' : null,
                Schema::hasColumn('borrowings', 'supporting_document_size') ? 'supporting_document_size' : null,
            ]));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
