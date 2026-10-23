<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_document_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('acceptance_required_version_id')->nullable()->after('publication_policy');
            $table->foreign('acceptance_required_version_id', 'legal_version_acceptance_baseline_fk')
                ->references('id')->on('legal_document_versions')->restrictOnDelete();
            $table->index(['legal_document_id', 'language', 'acceptance_required_version_id'], 'legal_version_acceptance_baseline_idx');
        });

        // Existing versions retain their historical, version-specific acceptance requirement.
        DB::table('legal_document_versions')->orderBy('id')->select('id')->chunkById(500, function ($versions): void {
            foreach ($versions as $version) {
                DB::table('legal_document_versions')->where('id', $version->id)
                    ->update(['acceptance_required_version_id' => $version->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('legal_document_versions', function (Blueprint $table) {
            $table->dropIndex('legal_version_acceptance_baseline_idx');
            $table->dropForeign('legal_version_acceptance_baseline_fk');
            $table->dropColumn('acceptance_required_version_id');
        });
    }
};
