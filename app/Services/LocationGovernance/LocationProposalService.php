<?php

namespace App\Services\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationType;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class LocationProposalService
{
    public function __construct(
        private readonly LocationDuplicateDetector $duplicateDetector,
    ) {
    }

    public function propose(User $proposer, Location $parent, LocationType $type, array $data): LocationProposal
    {
        $canonicalName = trim((string) ($data['canonical_name'] ?? ''));
        $normalizedName = $this->duplicateDetector->normalizeName($canonicalName);

        if ($normalizedName === '') {
            throw new DomainException('A canonical location name is required.');
        }

        $reusable = LocationProposal::query()
            ->where('parent_location_id', $parent->id)
            ->where('location_type_id', $type->id)
            ->where('normalized_name', $normalizedName)
            ->whereIn('status', [
                LocationProposalStatus::Pending->value,
                LocationProposalStatus::ReadyForReview->value,
                LocationProposalStatus::NeedsEvidence->value,
            ])
            ->orderBy('id')
            ->first();

        if ($reusable !== null) {
            return $reusable;
        }

        return LocationProposal::query()->create([
            'proposer_user_id' => $proposer->id,
            'parent_location_id' => $parent->id,
            'location_schema_id' => $parent->location_schema_id,
            'location_type_id' => $type->id,
            'country_code' => $parent->country_code,
            'canonical_name' => $canonicalName,
            'normalized_name' => $normalizedName,
            'localized_names' => $data['localized_names'] ?? null,
            'status' => LocationProposalStatus::Pending,
            'metadata' => $data['metadata'] ?? null,
            'audit_log' => [],
        ]);
    }

    public function support(LocationProposal $proposal, User $user, array $evidence): void
    {
        $this->guardOpen($proposal);

        DB::transaction(function () use ($proposal, $user, $evidence): void {
            $proposal->evidence()->updateOrCreate(
                ['user_id' => $user->id],
                ['evidence' => $evidence],
            );

            $proposal->refresh();
            $threshold = max(1, (int) config('location-governance.location_proposal_verification_threshold', 10));
            $distinctVerifiers = $proposal->evidence()->distinct()->count('user_id');

            if ($proposal->status === LocationProposalStatus::Pending && $distinctVerifiers >= $threshold) {
                $this->transition($proposal, LocationProposalStatus::ReadyForReview, $user, 'verification_threshold_reached');
            }
        });
    }

    public function requestMoreEvidence(LocationProposal $proposal, User $reviewer, string $reason): void
    {
        $this->guardOpen($proposal);
        $this->transition($proposal, LocationProposalStatus::NeedsEvidence, $reviewer, $reason, true);
    }

    public function approve(LocationProposal $proposal, User $reviewer, string $reason): Location
    {
        $this->guardOpen($proposal);

        return DB::transaction(function () use ($proposal, $reviewer, $reason): Location {
            $proposal->refresh();

            $duplicate = $this->duplicateDetector->findLikelyDuplicate(
                $proposal->parentLocation,
                $proposal->type,
                $proposal->canonical_name,
            );

            if ($duplicate !== null) {
                throw new DomainException('A matching canonical location already exists; merge the proposal instead.');
            }

            $location = Location::query()->create([
                'parent_id' => $proposal->parent_location_id,
                'location_schema_id' => $proposal->location_schema_id,
                'location_type_id' => $proposal->location_type_id,
                'country_code' => $proposal->country_code,
                'name' => $proposal->canonical_name,
                'canonical_name' => $proposal->canonical_name,
                'localized_names' => $proposal->localized_names,
                'level' => $proposal->type?->key,
                'status' => 'active',
                'provenance' => [
                    'source' => 'community_proposal',
                    'location_proposal_id' => $proposal->id,
                ],
            ]);

            $proposal->resolved_location_id = $location->id;
            $proposal->approved_at = now();
            $proposal->save();
            $this->transition($proposal, LocationProposalStatus::Approved, $reviewer, $reason, true);

            return $location;
        });
    }

    public function reject(LocationProposal $proposal, User $reviewer, string $reason): void
    {
        $this->guardOpen($proposal);
        $this->transition($proposal, LocationProposalStatus::Rejected, $reviewer, $reason, true);
    }

    public function merge(LocationProposal $proposal, Location $existing, User $reviewer, string $reason): void
    {
        $this->guardOpen($proposal);

        DB::transaction(function () use ($proposal, $existing, $reviewer, $reason): void {
            $proposal->resolved_location_id = $existing->id;
            $proposal->save();
            $this->transition($proposal, LocationProposalStatus::Merged, $reviewer, $reason, true);
        });
    }

    private function transition(
        LocationProposal $proposal,
        LocationProposalStatus $to,
        User $actor,
        string $reason,
        bool $markReviewed = false,
    ): void {
        $proposal->refresh();
        $from = $proposal->status;
        $audit = $proposal->audit_log ?? [];
        $audit[] = [
            'from' => $from instanceof LocationProposalStatus ? $from->value : (string) $from,
            'to' => $to->value,
            'actor_user_id' => $actor->id,
            'reason' => $reason,
            'at' => now()->toIso8601String(),
        ];

        $proposal->status = $to;
        $proposal->audit_log = $audit;

        if ($markReviewed) {
            $proposal->reviewed_by_user_id = $actor->id;
            $proposal->review_reason = $reason;
        }

        $proposal->save();
    }

    private function guardOpen(LocationProposal $proposal): void
    {
        $proposal->refresh();

        if (in_array($proposal->status, [
            LocationProposalStatus::Approved,
            LocationProposalStatus::Rejected,
            LocationProposalStatus::Merged,
        ], true)) {
            throw new DomainException('This location proposal is already resolved.');
        }
    }
}
