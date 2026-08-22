<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election_responsibility_contract_versions', function (Blueprint $table) {
            $table->json('clause_manifest')->nullable()->after('body');
            $table->boolean('e0_compliant')->default(false)->after('clause_manifest');
            $table->string('change_reason', 500)->nullable()->after('created_by');
            $table->index(['position', 'e0_compliant', 'published_at'], 'election_contract_e0_compliance_index');
        });
    }

    public function down(): void
    {
        Schema::table('election_responsibility_contract_versions', function (Blueprint $table) {
            $table->dropIndex('election_contract_e0_compliance_index');
            $table->dropColumn(['clause_manifest', 'e0_compliant', 'change_reason']);
        });
    }
};
