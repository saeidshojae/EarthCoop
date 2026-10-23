<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_document_versions', function (Blueprint $table) {
            // Existing versions retain the conservative behavior: a new version
            // is treated as material unless an administrator explicitly chooses otherwise.
            $table->string('publication_policy', 20)->default('material')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('legal_document_versions', function (Blueprint $table) {
            $table->dropColumn('publication_policy');
        });
    }
};
