<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_vote_snapshot_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->unsignedInteger('snapshot_version')->default(1);
            $table->string('cycle_identifier', 96);
            $table->timestamp('stopped_at');
            $table->char('snapshot_hash', 64);
            $table->unsignedInteger('vote_count')->default(0);
            $table->timestamps();

            $table->unique(['election_id', 'snapshot_version'], 'election_vote_snapshot_run_unique');
            $table->index(['election_id', 'stopped_at'], 'election_vote_snapshot_run_stop_index');
        });

        Schema::create('election_vote_snapshot_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snapshot_run_id');
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('voter_id');
            $table->unsignedBigInteger('candidate_user_id');
            $table->string('position', 24);
            $table->timestamps();

            $table->index(['snapshot_run_id', 'position', 'candidate_user_id'], 'election_vote_snapshot_tally_index');
            $table->index(['election_id', 'voter_id'], 'election_vote_snapshot_voter_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_vote_snapshot_entries');
        Schema::dropIfExists('election_vote_snapshot_runs');
    }
};
