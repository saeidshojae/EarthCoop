<?php

namespace App\Services\NajmHoda;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\LocationProposal;
use App\Services\LocationGovernance\LocationDuplicateDetector;

class LocationGovernanceReviewService
{
    public function __construct(
        private readonly LocationDuplicateDetector $duplicateDetector,
    ) {
    }

    /**
     * Review a proposal without mutating it. Najm Hoda may recommend a human
     * decision, but it never approves, rejects or merges location proposals.
     *
     * @return array<string, mixed>
     */
    public function review(LocationProposal $proposal): array
    {
        $proposal->loadMissing(['parentLocation', 'type']);

        $distinctVerifiers = $proposal->evidence()->distinct()->count('user_id');
        $duplicate = null;
        $anomalies = [];

        if ($proposal->parentLocation === null) {
            $anomalies[] = 'missing_parent_location';
        }
        if ($proposal->type === null) {
            $anomalies[] = 'missing_location_type';
        }

        if ($proposal->parentLocation !== null && $proposal->type !== null) {
            $duplicate = $this->duplicateDetector->findLikelyDuplicate(
                $proposal->parentLocation,
                $proposal->type,
                (string) $proposal->canonical_name,
            );
        }

        [$recommendation, $rationale] = $this->recommendationFor(
            $proposal,
            $duplicate?->id,
            $distinctVerifiers,
            $anomalies,
        );

        $status = $proposal->status instanceof LocationProposalStatus
            ? $proposal->status->value
            : (string) $proposal->status;

        return [
            'proposal_id' => $proposal->id,
            'status' => $status,
            'recommendation' => $recommendation,
            'rationale' => $rationale,
            'duplicate_candidate_id' => $duplicate?->id,
            'distinct_verifiers' => $distinctVerifiers,
            'anomalies' => $anomalies,
            'human_approval_required' => true,
        ];
    }

    /**
     * @param array<int, string> $anomalies
     * @return array{0: string, 1: string}
     */
    private function recommendationFor(
        LocationProposal $proposal,
        ?int $duplicateId,
        int $distinctVerifiers,
        array $anomalies,
    ): array {
        if ($duplicateId !== null) {
            return ['merge', 'A matching active canonical location already exists under the same parent and type.'];
        }

        if ($anomalies !== []) {
            return ['review', 'Structural anomalies require human review before any location-governance write.'];
        }

        if ($proposal->status === LocationProposalStatus::NeedsEvidence) {
            return ['needs_evidence', 'The proposal is explicitly waiting for additional evidence.'];
        }

        $threshold = max(1, (int) config('location-governance.location_proposal_verification_threshold', 10));
        if ($proposal->status === LocationProposalStatus::ReadyForReview && $distinctVerifiers >= $threshold) {
            return ['approve', 'Distinct-user verification threshold is met; final approval still requires a human administrator.'];
        }

        return ['review', 'The proposal remains open and needs a human governance review.'];
    }
}
