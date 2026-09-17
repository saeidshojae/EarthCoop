<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_proposals', function (Blueprint $table) {
            $table->foreignId('parent_location_id')->nullable()->change();
            $table->foreignId('parent_location_proposal_id')
                ->nullable()
                ->after('parent_location_id')
                ->constrained('location_proposals')
                ->cascadeOnDelete();
            $table->index(
                ['parent_location_proposal_id', 'location_type_id', 'normalized_name'],
                'location_proposals_proposal_parent_lookup'
            );
        });
    }

    public function down(): void
    {
        $hasNestedOrUnanchoredProposals = DB::table('location_proposals')
            ->whereNotNull('parent_location_proposal_id')
            ->orWhereNull('parent_location_id')
            ->exists();

        if ($hasNestedOrUnanchoredProposals) {
            throw new RuntimeException(
                'Cannot roll back location proposal chains while proposals depend on a proposal parent or lack a canonical parent.'
            );
        }

        Schema::table('location_proposals', function (Blueprint $table) {
            $table->dropIndex('location_proposals_proposal_parent_lookup');
            $table->dropConstrainedForeignId('parent_location_proposal_id');
        });

        Schema::table('location_proposals', function (Blueprint $table) {
            $table->foreignId('parent_location_id')->nullable(false)->change();
        });
    }
};
