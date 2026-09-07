<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->string('vote_visibility', 32)->default('confidential')->after('position');
            $table->index(['election_id', 'vote_visibility'], 'votes_visibility_index');
        });

        Schema::table('election_ballot_events', function (Blueprint $table) {
            $table->string('vote_visibility', 32)->default('confidential')->after('previous_position');
            $table->boolean('comment_anonymous')->default(false)->after('comment_visibility');
        });
    }

    public function down(): void
    {
        Schema::table('election_ballot_events', function (Blueprint $table) {
            $table->dropColumn(['vote_visibility', 'comment_anonymous']);
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex('votes_visibility_index');
            $table->dropColumn('vote_visibility');
        });
    }
};
