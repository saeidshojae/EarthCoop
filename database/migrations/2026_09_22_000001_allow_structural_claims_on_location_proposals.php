<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_structure_claims', function (Blueprint $table): void {
            $table->foreignId('location_proposal_id')->nullable()->after('location_id')->constrained('location_proposals')->cascadeOnDelete();
            $table->index(['location_proposal_id', 'claim_type', 'status'], 'lsc_proposal_open_lookup');
        });
        Schema::table('location_structure_claims', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('location_structure_claims', function (Blueprint $table): void {
            $table->dropIndex('lsc_proposal_open_lookup');
            $table->dropConstrainedForeignId('location_proposal_id');
        });
        Schema::table('location_structure_claims', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable(false)->change();
        });
    }
};
