<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_tally_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('candidate_user_id');
            $table->string('position', 24);
            $table->unsignedInteger('vote_count');
            $table->unsignedInteger('rank');
            $table->boolean('within_seat_cutoff')->default(false);
            $table->string('tie_break_version', 64);
            $table->char('tie_break_key', 64);
            $table->timestamp('tallied_at');
            $table->timestamps();

            $table->unique(
                ['election_id', 'position', 'candidate_user_id'],
                'election_tally_results_candidate_unique'
            );
            $table->unique(
                ['election_id', 'position', 'rank'],
                'election_tally_results_rank_unique'
            );
            $table->index(
                ['election_id', 'position', 'within_seat_cutoff'],
                'election_tally_results_cutoff_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_tally_results');
    }
};
