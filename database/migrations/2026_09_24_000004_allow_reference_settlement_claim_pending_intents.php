<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_residence_intents', function (Blueprint $table): void {
            $table->dropForeign(['location_proposal_id']);
            $table->unsignedBigInteger('location_proposal_id')->nullable()->change();
            $table->foreign('location_proposal_id', 'pending_residence_proposal_fk')
                ->references('id')->on('location_proposals')->cascadeOnDelete();
            $table->foreignId('reference_settlement_residence_claim_id')->nullable()
                ->after('location_proposal_id');
            $table->foreign('reference_settlement_residence_claim_id', 'pending_residence_settlement_claim_fk')
                ->references('id')->on('reference_settlement_residence_claims')->cascadeOnDelete();
            $table->index(
                ['reference_settlement_residence_claim_id', 'status'],
                'pending_residence_settlement_claim_status_idx'
            );
        });
    }

    public function down(): void
    {
        if (DB::table('pending_residence_intents')
            ->whereNotNull('reference_settlement_residence_claim_id')
            ->exists()) {
            throw new RuntimeException(
                'Cannot roll back settlement-backed pending residence support while settlement intents still exist.'
            );
        }

        Schema::table('pending_residence_intents', function (Blueprint $table): void {
            $table->dropForeign('pending_residence_settlement_claim_fk');
            $table->dropIndex('pending_residence_settlement_claim_status_idx');
            $table->dropColumn('reference_settlement_residence_claim_id');
            $table->dropForeign('pending_residence_proposal_fk');
            $table->unsignedBigInteger('location_proposal_id')->nullable(false)->change();
            $table->foreign('location_proposal_id')->references('id')->on('location_proposals')->cascadeOnDelete();
        });
    }
};
