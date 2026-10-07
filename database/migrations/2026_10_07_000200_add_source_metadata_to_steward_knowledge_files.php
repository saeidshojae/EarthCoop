<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('steward_knowledge_files', function (Blueprint $table) {
            $table->string('source_type', 20)->default('file')->after('title');
            $table->text('source_url')->nullable()->after('source_type');
            $table->string('original_filename', 255)->nullable()->change();
            $table->string('file_path', 500)->nullable()->change();
            $table->string('file_type', 50)->nullable()->change();
            $table->integer('file_size')->nullable()->change();

            $table->index('source_type');
        });
    }

    public function down(): void
    {
        $hasUrlSources = DB::table('steward_knowledge_files')
            ->where('source_type', 'url')
            ->exists();

        $hasNullLegacyFields = DB::table('steward_knowledge_files')
            ->where(function ($query) {
                $query->whereNull('original_filename')
                    ->orWhereNull('file_path')
                    ->orWhereNull('file_type')
                    ->orWhereNull('file_size');
            })
            ->exists();

        if ($hasUrlSources || $hasNullLegacyFields) {
            throw new \RuntimeException(
                'Cannot rollback steward source metadata while URL sources or nullable source fields exist.'
            );
        }

        Schema::table('steward_knowledge_files', function (Blueprint $table) {
            $table->dropIndex(['source_type']);
            $table->dropColumn(['source_type', 'source_url']);
            $table->string('original_filename', 255)->nullable(false)->change();
            $table->string('file_path', 500)->nullable(false)->change();
            $table->string('file_type', 50)->nullable(false)->change();
            $table->integer('file_size')->nullable(false)->change();
        });
    }
};
