<?php

namespace App\Services\Elections;

final class LegacyVoteCandidateIdentityResolver
{
    /**
     * Resolve the selected User id from the overloaded legacy candidate_id.
     *
     * Returning null is intentional: ambiguity is safer than silently assigning
     * a vote to the wrong member.
     */
    public function resolve(
        int $legacyId,
        bool $directUserExists,
        ?int $candidateUserId,
        bool $candidateUserExists,
        bool $candidateMatchesElection,
    ): ?int {
        if ($legacyId <= 0) {
            return null;
        }

        if ($directUserExists) {
            $conflictsWithCandidateMeaning = $candidateMatchesElection
                && $candidateUserExists
                && $candidateUserId !== null
                && $candidateUserId !== $legacyId;

            return $conflictsWithCandidateMeaning ? null : $legacyId;
        }

        if (
            $candidateUserId !== null
            && $candidateUserExists
            && $candidateMatchesElection
        ) {
            return $candidateUserId;
        }

        return null;
    }
}
