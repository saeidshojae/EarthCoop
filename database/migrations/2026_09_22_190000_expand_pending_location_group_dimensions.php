<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_scoped_group_requests', function (Blueprint $table): void {
            $table->string('dimension_key', 64)->nullable()->after('scope_kind');
            $table->string('dimension_value_key', 191)->nullable()->after('dimension_key');
        });

        DB::table('location_scoped_group_requests')->where('scope_kind', 'official_public')->update([
            'scope_kind' => 'official_system',
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);

        Schema::table('location_scoped_group_requests', function (Blueprint $table): void {
            $table->dropUnique('location_group_request_user_proposal_scope_unique');
            $table->dropUnique('loc_group_req_user_claim_scope_uq');
            $table->unique(['requester_user_id', 'location_proposal_id', 'scope_kind', 'dimension_key', 'dimension_value_key'], 'loc_grp_req_user_prop_dim_uq');
            $table->unique(['requester_user_id', 'location_structure_claim_id', 'scope_kind', 'dimension_key', 'dimension_value_key'], 'loc_grp_req_user_claim_dim_uq');
            $table->index(['requester_user_id', 'dimension_key', 'status'], 'loc_grp_req_user_dim_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('location_scoped_group_requests', function (Blueprint $table): void {
            $table->dropUnique('loc_grp_req_user_prop_dim_uq');
            $table->dropUnique('loc_grp_req_user_claim_dim_uq');
            $table->dropIndex('loc_grp_req_user_dim_status_idx');
        });
        DB::table('location_scoped_group_requests')->where('scope_kind', 'official_system')->where('dimension_key', '!=', 'public')->delete();
        DB::table('location_scoped_group_requests')->where('scope_kind', 'official_system')->update(['scope_kind' => 'official_public']);
        Schema::table('location_scoped_group_requests', function (Blueprint $table): void {
            $table->dropColumn(['dimension_key', 'dimension_value_key']);
            $table->unique(['requester_user_id', 'location_proposal_id', 'scope_kind'], 'location_group_request_user_proposal_scope_unique');
            $table->unique(['requester_user_id', 'location_structure_claim_id', 'scope_kind'], 'loc_group_req_user_claim_scope_uq');
        });
    }
};
