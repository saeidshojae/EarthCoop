<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_scoped_group_requests', function (Blueprint $table): void {
            $table->foreignId('reference_settlement_residence_claim_id')->nullable()
                ->after('location_structure_claim_id');
            $table->foreign('reference_settlement_residence_claim_id', 'loc_grp_req_settlement_claim_fk')
                ->references('id')->on('reference_settlement_residence_claims')->cascadeOnDelete();
            $table->unique(
                ['requester_user_id', 'reference_settlement_residence_claim_id', 'scope_kind', 'dimension_key', 'dimension_value_key'],
                'loc_grp_req_user_settle_dim_uq'
            );
            $table->index(
                ['reference_settlement_residence_claim_id', 'status'],
                'loc_grp_req_settle_status_idx'
            );
        });
    }

    public function down(): void
    {
        if (DB::table('location_scoped_group_requests')
            ->whereNotNull('reference_settlement_residence_claim_id')
            ->exists()) {
            throw new RuntimeException(
                'Cannot roll back settlement-backed pending group support while settlement group requests still exist.'
            );
        }

        Schema::table('location_scoped_group_requests', function (Blueprint $table): void {
            $table->dropUnique('loc_grp_req_user_settle_dim_uq');
            $table->dropIndex('loc_grp_req_settle_status_idx');
            $table->dropForeign('loc_grp_req_settlement_claim_fk');
            $table->dropColumn('reference_settlement_residence_claim_id');
        });
    }
};
