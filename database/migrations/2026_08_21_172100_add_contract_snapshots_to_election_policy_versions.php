<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election_policy_versions', function (Blueprint $table) {
            $table->foreignId('manager_contract_version_id')->nullable()
                ->after('response_duration_days')
                ->constrained('election_responsibility_contract_versions')->nullOnDelete();
            $table->foreignId('inspector_contract_version_id')->nullable()
                ->after('manager_contract_version_id')
                ->constrained('election_responsibility_contract_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('election_policy_versions', function (Blueprint $table) {
            $table->dropForeign(['manager_contract_version_id']);
            $table->dropForeign(['inspector_contract_version_id']);
            $table->dropColumn(['manager_contract_version_id', 'inspector_contract_version_id']);
        });
    }
};
