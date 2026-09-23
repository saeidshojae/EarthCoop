<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\User;
use App\Models\UserLocationRelationship;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReferenceSettlementResidenceClaimService
{
    public function resolveAnchor(ReferenceSettlement $settlement): Location
    {
        $identity = LocationExternalId::query()
            ->with('location.type')
            ->where('source', \App\Services\LocationGovernance\Import\ReferenceGeographyImporter::SOURCE)
            ->where('dataset_version', 'v2')
            ->where('external_id', $settlement->parent_external_id)
            ->first();

        $location = $identity?->location;
        if ($location === null || $location->status !== 'active' || $location->country_code !== 'IR') {
            throw ValidationException::withMessages([
                'reference_settlement_external_id' => 'والد رسمی این آبادی هنوز در ساختار فعال مکان قابل تطبیق نیست.',
            ]);
        }

        return $location;
    }

    public function claim(
        User $user,
        ReferenceSettlement $settlement,
        ?UserLocationRelationship $anchorRelationship = null,
    ): ReferenceSettlementResidenceClaim {
        return DB::transaction(function () use ($user, $settlement, $anchorRelationship): ReferenceSettlementResidenceClaim {
            $locked = ReferenceSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            $isUnverified = in_array($locked->classification, ['unverified_settlement', 'needs_review'], true)
                && $locked->residential_eligibility === 'unverified';
            $hasVerifiedResidentialEvidence = $locked->classification === 'verified_residential_village'
                && $locked->residential_eligibility === 'verified';

            if ((! $isUnverified && ! $hasVerifiedResidentialEvidence)
                || $locked->governance_authorized
                || $locked->operational_promotion_allowed) {
                throw ValidationException::withMessages([
                    'reference_settlement_external_id' => 'این آبادی در وضعیت قابل ثبت برای درخواست سکونت نیست.',
                ]);
            }

            $claim = ReferenceSettlementResidenceClaim::query()->firstOrCreate(
                ['reference_settlement_id' => $locked->id, 'user_id' => $user->id],
                [
                    'status' => $hasVerifiedResidentialEvidence
                        ? 'residential_evidence_verified'
                        : ($locked->classification === 'needs_review' ? 'needs_evidence' : 'pending'),
                    'submitted_at' => now(),
                    'anchor_relationship_id' => $anchorRelationship?->id,
                ],
            );

            if ($anchorRelationship !== null && $claim->anchor_relationship_id === null) {
                $claim->forceFill(['anchor_relationship_id' => $anchorRelationship->id])->save();
            }

            return $claim;
        });
    }
}
