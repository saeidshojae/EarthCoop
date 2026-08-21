<?php

use App\Services\Elections\LegacyVoteCandidateIdentityResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsCandidateUserId = ! Schema::hasColumn('votes', 'candidate_user_id');
        $needsPosition = ! Schema::hasColumn('votes', 'position');

        if ($needsCandidateUserId || $needsPosition) {
            Schema::table('votes', function (Blueprint $table) use ($needsCandidateUserId, $needsPosition) {
                if ($needsCandidateUserId) {
                    $table->unsignedBigInteger('candidate_user_id')
                        ->nullable()
                        ->after('candidate_id')
                        ->index('votes_candidate_user_id_index');
                }

                // The runtime has historically written a vote position even
                // though older database snapshots did not consistently contain
                // the column.
                if ($needsPosition) {
                    $table->string('position', 32)->nullable()->after('candidate_user_id');
                }
            });
        }

        $this->backfillProvableCandidateUsers();
    }

    public function down(): void
    {
        if (Schema::hasColumn('votes', 'candidate_user_id')) {
            Schema::table('votes', function (Blueprint $table) {
                $table->dropIndex('votes_candidate_user_id_index');
                $table->dropColumn('candidate_user_id');
            });
        }

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
        $resolver = new LegacyVoteCandidateIdentityResolver();

        DB::table('votes')
            ->whereNull('candidate_user_id')
            ->orderBy('id')
            ->chunkById(500, function ($votes) use ($resolver): void {
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
                    $candidateUserExists = $candidateUserId !== null
                        && DB::table('users')->where('id', $candidateUserId)->exists();
                    $candidateMatchesElection = $candidate !== null
                        && (int) $candidate->election_id === (int) $vote->election_id;

                    $resolved = $resolver->resolve(
                        legacyId: $legacyId,
                        directUserExists: $directUserExists,
                        candidateUserId: $candidateUserId,
                        candidateUserExists: $candidateUserExists,
                        candidateMatchesElection: $candidateMatchesElection,
                    );

                    if ($resolved !== null) {
                        DB::table('votes')
                            ->where('id', $vote->id)
                            ->update(['candidate_user_id' => $resolved]);
                    }
                }
            }, 'id');
    }
};
