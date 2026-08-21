<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->index(['election_id', 'voter_id'], 'votes_election_voter_index');
            $table->index(
                ['election_id', 'candidate_user_id', 'position'],
                'votes_election_candidate_position_index'
            );
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->index(['election_id', 'user_id'], 'candidates_election_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex('votes_election_voter_index');
            $table->dropIndex('votes_election_candidate_position_index');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex('candidates_election_user_index');
        });
    }
};
