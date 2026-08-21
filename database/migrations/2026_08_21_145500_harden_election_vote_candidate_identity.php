<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            if (! Schema::hasColumn('votes', 'candidate_user_id')) {
                $table->unsignedBigInteger('candidate_user_id')
                    ->nullable()
                    ->after('candidate_id')
                    ->index('votes_candidate_user_id_index');
            }

            // The runtime has historically written a vote position even though
            // older database snapshots did not consistently contain the column.
            if (! Schema::hasColumn('votes', 'position')) {
                $table->string('position', 32)->nullable()->after('candidate_user_id');
            }
        });

        $this->backfillProvableCandidateUsers();
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            if (Schema::hasColumn('votes', 'candidate_user_id')) {
                $table->dropIndex('votes_candidate_user_id_index');
                $table->dropColumn('candidate_user_id');
            }
        });

        // Do not drop `position` on rollback. It predates this migration at the
        // application-contract level, and removing it could destroy valid data
        // on installations where the column was supplied by an older patch.
    }

    /**
     * Populate the canonical selected-member id only where the legacy value can
     * be resolved without guessing.
     *
     * Legacy `votes.candidate_id` has been used with two incompatible meanings:
     * a User id in the active ballot flow, and a Candidate id in model relations.
     * If both interpretations exist and disagree, the row remains NULL so the
     * reconciliation audit can surface it for explicit review.
     */
    private function backfillProvableCandidateUsers(): void
    {
        DB::table('votes')
            ->whereNull('candidate_user_id')
            ->orderBy('id')
            ->chunkById(500, function ($votes): void {
                foreach ($votes as $vote) {
                    $legacyId = (int) $vote->candidate_id;

                    if ($legacyId <= 0) {
                        continue;
                    }

                    $directUserExists = DB::table('users')
                        ->where('id', $legacyId)
                        ->exists();

                    $candidate = DB::table('candidates')
                        ->where('id', $legacyId)
                        ->first(['id', 'user_id', 'election_id']);

                    $candidateUserId = $candidate ? (int) $candidate->user_id : null;
                    $candidateUserExists = $candidateUserId
                        ? DB::table('users')->where('id', $candidateUserId)->exists()
                        : false;

                    $candidateMatchesElection = ! $candidate
                        || (int) $candidate->election_id === (int) $vote->election_id;

                    $resolved = null;

                    if ($directUserExists) {
                        // If the same numeric id also identifies a Candidate that
                        // points at another user in this election, interpretation
                        // is genuinely ambiguous and must not be guessed.
                        $conflictsWithCandidateMeaning = $candidate
                            && $candidateMatchesElection
                            && $candidateUserExists
                            && $candidateUserId !== $legacyId;

                        if (! $conflictsWithCandidateMeaning) {
                            $resolved = $legacyId;
                        }
                    } elseif ($candidate && $candidateMatchesElection && $candidateUserExists) {
                        $resolved = $candidateUserId;
                    }

                    if ($resolved !== null) {
                        DB::table('votes')
                            ->where('id', $vote->id)
                            ->update(['candidate_user_id' => $resolved]);
                    }
                }
            }, 'id');
    }
};
