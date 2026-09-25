<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_proposals', function (Blueprint $table): void {
            $table->foreignId('parent_reference_settlement_id')->nullable()->after('parent_location_proposal_id');
            $table->foreign('parent_reference_settlement_id', 'location_proposals_reference_settlement_parent_fk')
                ->references('id')->on('reference_settlements')->restrictOnDelete();
            $table->index(['parent_reference_settlement_id', 'location_type_id', 'status'], 'location_proposals_reference_settlement_parent_status_idx');
        });
    }

    public function down(): void
    {
        if (DB::table('location_proposals')->whereNotNull('parent_reference_settlement_id')->exists()) {
            throw new RuntimeException('Cannot roll back reference-settlement proposal support while settlement-backed proposals still exist.');
        }
        Schema::table('location_proposals', function (Blueprint $table): void {
            $table->dropForeign('location_proposals_reference_settlement_parent_fk');
            $table->dropIndex('location_proposals_reference_settlement_parent_status_idx');
            $table->dropColumn('parent_reference_settlement_id');
        });
    }
};
