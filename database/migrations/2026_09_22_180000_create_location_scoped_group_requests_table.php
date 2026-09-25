<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_scoped_group_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('location_proposal_id')->nullable()->constrained('location_proposals')->cascadeOnDelete();
            $table->unsignedBigInteger('location_structure_claim_id')->nullable();
            $table->foreign('location_structure_claim_id', 'loc_group_req_struct_claim_fk')->references('id')->on('location_structure_claims')->cascadeOnDelete();
            $table->string('scope_kind');
            $table->string('status')->default('pending_location')->index();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('governance_area_id')->nullable()->constrained('governance_areas')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['requester_user_id', 'location_proposal_id', 'scope_kind'], 'location_group_request_user_proposal_scope_unique');
            $table->unique(['requester_user_id', 'location_structure_claim_id', 'scope_kind'], 'loc_group_req_user_claim_scope_uq');
            $table->index(['requester_user_id', 'scope_kind', 'status'], 'location_group_request_user_scope_status_index');
        });
    }
    public function down(): void { Schema::dropIfExists('location_scoped_group_requests'); }
};
