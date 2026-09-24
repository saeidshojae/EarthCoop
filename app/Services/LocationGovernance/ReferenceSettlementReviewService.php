<?php

namespace App\Services\LocationGovernance;

use App\Models\ReferenceSettlement;
use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\LocationProposal;
use App\Models\LocationScopedGroupRequest;
use App\Models\ReferenceSettlementReview;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReferenceSettlementReviewService
{
    public const DECISION_NEEDS_EVIDENCE = 'needs_evidence';
    public const DECISION_RESIDENTIAL = 'verified_residential_village';
    public const DECISION_NONRESIDENTIAL = 'verified_nonresidential_place';

    public function review(
        ReferenceSettlement $settlement,
        User $actor,
        string $decision,
        string $reason,
        ?string $evidenceSource = null,
        ?string $evidenceDate = null,
        ?string $evidenceReference = null,
    ): ReferenceSettlementReview {
        if (! in_array($decision, [
            self::DECISION_NEEDS_EVIDENCE,
            self::DECISION_RESIDENTIAL,
            self::DECISION_NONRESIDENTIAL,
        ], true)) {
            throw new DomainException('Unsupported settlement review decision.');
        }

        if (! in_array($settlement->classification, ['unverified_settlement', 'needs_review'], true)) {
            throw new DomainException('Reviewed settlement classification requires a separate correction workflow.');
        }

        if ($settlement->governance_authorized || $settlement->operational_promotion_allowed) {
            throw new DomainException('Settlement catalog review cannot modify governance-authorized records.');
        }

        if (in_array($decision, [self::DECISION_RESIDENTIAL, self::DECISION_NONRESIDENTIAL], true)
            && (blank($evidenceSource) || blank($evidenceDate) || blank($evidenceReference))) {
            throw new DomainException('Classification requires dated source-backed evidence and an evidence reference.');
        }

        return DB::transaction(function () use (
            $settlement, $actor, $decision, $reason, $evidenceSource, $evidenceDate, $evidenceReference
        ): ReferenceSettlementReview {
            $locked = ReferenceSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if (! in_array($locked->classification, ['unverified_settlement', 'needs_review'], true)) {
                throw new DomainException('Reviewed settlement classification requires a separate correction workflow.');
            }
            if ($locked->governance_authorized || $locked->operational_promotion_allowed) {
                throw new DomainException('Settlement catalog review cannot modify governance-authorized records.');
            }
            $before = [
                'classification' => $locked->classification,
                'residential_eligibility' => $locked->residential_eligibility,
                'governance_authorized' => (bool) $locked->governance_authorized,
                'operational_promotion_allowed' => (bool) $locked->operational_promotion_allowed,
            ];

            if ($decision === self::DECISION_RESIDENTIAL) {
                $locked->forceFill([
                    'classification' => self::DECISION_RESIDENTIAL,
                    'residential_eligibility' => 'verified',
                    // Residential evidence is NOT governance authorization.
                    'governance_authorized' => false,
                    'operational_promotion_allowed' => false,
                ])->save();

                ReferenceSettlementResidenceClaim::query()
                    ->where('reference_settlement_id', $locked->id)
                    ->whereIn('status', ['pending', 'needs_evidence'])
                    ->update([
                        'status' => 'residential_evidence_verified',
                        'reviewed_at' => now(),
                        'reviewed_by_user_id' => $actor->id,
                        'updated_at' => now(),
                    ]);
            } elseif ($decision === self::DECISION_NONRESIDENTIAL) {
                $locked->forceFill([
                    'classification' => self::DECISION_NONRESIDENTIAL,
                    'residential_eligibility' => 'ineligible',
                    'governance_authorized' => false,
                    'operational_promotion_allowed' => false,
                ])->save();

                $claims = ReferenceSettlementResidenceClaim::query()
                    ->where('reference_settlement_id', $locked->id)
                    ->whereIn('status', ['pending', 'needs_evidence', 'residential_evidence_verified'])
                    ->lockForUpdate()
                    ->get();

                foreach ($claims as $claim) {
                    $claim->forceFill([
                        'status' => 'rejected',
                        'reviewed_at' => now(),
                        'reviewed_by_user_id' => $actor->id,
                    ])->save();
                    app(ResidenceService::class)->cancelPendingReferenceSettlementIntent(
                        $claim,
                        'reference_settlement_classified_nonresidential',
                    );
                    app(\App\Services\Groups\PendingLocationGroupRequestService::class)
                        ->rejectForReferenceSettlementClaim($claim);
                }

                $openChildren = LocationProposal::query()
                    ->where('parent_reference_settlement_id', $locked->id)
                    ->whereIn('status', [
                        LocationProposalStatus::Pending->value,
                        LocationProposalStatus::ReadyForReview->value,
                        LocationProposalStatus::NeedsEvidence->value,
                    ])->lockForUpdate()->get();
                foreach ($openChildren as $child) {
                    $this->rejectOpenProposalTree(
                        $child,
                        $actor,
                        'والد آبادی مرجع غیرمسکونی تشخیص داده شد: '.$reason,
                    );
                }

                $rejectedClaimIds = ReferenceSettlementResidenceClaim::query()
                    ->where('reference_settlement_id', $locked->id)
                    ->where('status', 'rejected')
                    ->pluck('id');
                if ($rejectedClaimIds->isNotEmpty()) {
                    LocationScopedGroupRequest::query()
                        ->whereIn('reference_settlement_residence_claim_id', $rejectedClaimIds)
                        ->whereIn('status', ['pending_location', 'ready_to_materialize'])
                        ->update(['status' => 'rejected', 'updated_at' => now()]);
                }
            } else {
                $locked->forceFill([
                    'classification' => 'needs_review',
                    'residential_eligibility' => 'unverified',
                    'governance_authorized' => false,
                    'operational_promotion_allowed' => false,
                ])->save();

                ReferenceSettlementResidenceClaim::query()
                    ->where('reference_settlement_id', $locked->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'needs_evidence',
                        'reviewed_at' => now(),
                        'reviewed_by_user_id' => $actor->id,
                        'updated_at' => now(),
                    ]);
            }

            return ReferenceSettlementReview::query()->create([
                'reference_settlement_id' => $locked->id,
                'reviewed_by_user_id' => $actor->id,
                'decision' => $decision,
                'reason' => $reason,
                'evidence_source' => $evidenceSource,
                'evidence_date' => $evidenceDate,
                'evidence_reference' => $evidenceReference,
                'snapshot' => [
                    'before' => $before,
                    'after' => [
                        'classification' => $locked->classification,
                        'residential_eligibility' => $locked->residential_eligibility,
                        'governance_authorized' => (bool) $locked->governance_authorized,
                        'operational_promotion_allowed' => (bool) $locked->operational_promotion_allowed,
                    ],
                ],
            ]);
        });
    }
    private function rejectOpenProposalTree(LocationProposal $proposal, User $actor, string $reason): void
    {
        $openStatuses = [
            LocationProposalStatus::Pending->value,
            LocationProposalStatus::ReadyForReview->value,
            LocationProposalStatus::NeedsEvidence->value,
        ];

        $children = $proposal->childProposals()
            ->whereIn('status', $openStatuses)
            ->lockForUpdate()
            ->get();

        foreach ($children as $child) {
            $this->rejectOpenProposalTree($child, $actor, $reason);
        }

        app(LocationProposalService::class)->reject($proposal->fresh(), $actor, $reason);
    }

}
