<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_ballot_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('voter_id');
            $table->string('event_type', 32);
            $table->unsignedBigInteger('candidate_user_id')->nullable();
            $table->unsignedBigInteger('previous_candidate_user_id')->nullable();
            $table->string('position', 24)->nullable();
            $table->string('previous_position', 24)->nullable();
            $table->text('comment')->nullable();
            $table->string('comment_visibility', 32)->nullable();
            $table->string('request_uuid', 128);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['election_id', 'voter_id', 'occurred_at'], 'election_ballot_events_voter_timeline');
            $table->index(['election_id', 'candidate_user_id'], 'election_ballot_events_candidate_index');
            $table->index(['request_uuid'], 'election_ballot_events_request_index');
            $table->index(['election_id', 'comment_visibility'], 'election_ballot_events_visibility_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_ballot_events');
    }
};
