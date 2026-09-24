<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_structure_claims', function (Blueprint $table): void {
            $table->foreignId('reference_settlement_id')
                ->nullable()
                ->after('location_proposal_id')
                ->constrained('reference_settlements')
                ->cascadeOnDelete();
            $table->index(
                ['reference_settlement_id', 'claim_type', 'status'],
                'lsc_reference_settlement_open_lookup'
            );
        });
    }

    public function down(): void
    {
        if (DB::table('location_structure_claims')->whereNotNull('reference_settlement_id')->exists()) {
            throw new \RuntimeException(
                'Cannot remove reference-settlement structural-claim support while dependent claims exist.'
            );
        }

        Schema::table('location_structure_claims', function (Blueprint $table): void {
            $table->dropIndex('lsc_reference_settlement_open_lookup');
            $table->dropConstrainedForeignId('reference_settlement_id');
        });
    }
};
