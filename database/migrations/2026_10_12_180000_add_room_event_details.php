<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_borrowings', function (Blueprint $table) {
            $table->string('activity_type', 40)->nullable();
            $table->string('documentation_url', 2048)->nullable();
            $table->boolean('uses_budget')->default(false);
            $table->string('budget_type')->nullable();
            $table->boolean('participant_internal')->default(false);
            $table->boolean('participant_external')->default(false);
            $table->string('approval_document_path')->nullable();
            $table->string('approval_document_original_name')->nullable();
            $table->unsignedInteger('approval_document_size')->nullable();
            $table->string('layout_document_path')->nullable();
            $table->string('layout_document_original_name')->nullable();
            $table->unsignedInteger('layout_document_size')->nullable();
            $table->string('participant_document_path')->nullable();
            $table->string('participant_document_original_name')->nullable();
            $table->unsignedInteger('participant_document_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_borrowings', function (Blueprint $table) {
            $table->dropColumn([
                'activity_type',
                'documentation_url',
                'uses_budget',
                'budget_type',
                'participant_internal',
                'participant_external',
                'approval_document_path',
                'approval_document_original_name',
                'approval_document_size',
                'layout_document_path',
                'layout_document_original_name',
                'layout_document_size',
                'participant_document_path',
                'participant_document_original_name',
                'participant_document_size',
            ]);
        });
    }
};
