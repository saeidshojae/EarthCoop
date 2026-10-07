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
            $table->longText('extracted_content')->nullable()->change();
        });
    }

    public function down(): void
    {
        $oversized = DB::table('steward_knowledge_files')
            ->whereRaw('LENGTH(extracted_content) > 65535')
            ->exists();

        if ($oversized) {
            throw new \RuntimeException(
                'Cannot rollback steward extracted_content to TEXT while rows exceed 65535 bytes.'
            );
        }

        Schema::table('steward_knowledge_files', function (Blueprint $table) {
            $table->text('extracted_content')->nullable()->change();
        });
    }
};
