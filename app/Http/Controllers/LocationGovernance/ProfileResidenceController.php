<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Profile\ProfileController;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\LocationStructureClaim;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\LocationGovernance\IranSettlementAnchorResolver;
use App\Services\LocationGovernance\LocationProposalPolicy;
use App\Services\LocationGovernance\LocationTreeResolver;
use App\Services\LocationGovernance\ResidenceService;
use App\Services\ProfileCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProfileResidenceController extends Controller
{
    public function update(
        Request $request,
        LocationTreeResolver $locationTreeResolver,
        ResidenceService $residenceService,
        ProfileCompletionService $profileCompletionService,
        LocationProposalPolicy $proposalPolicy,
        IranSettlementAnchorResolver $settlementAnchorResolver,
    ): RedirectResponse {
        if (! (bool) config('location-governance.registration_enabled')) {
            return app(ProfileController::class)->updateAddress($request);
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_proposal_id' => ['nullable', 'integer', 'exists:location_proposals,id'],
            'reference_settlement_external_id' => ['nullable', 'string', 'regex:/^IR-1404-[1-9][0-9]*$/D'],
            'location_structure_claim_ids' => ['nullable', 'array'],
            'location_structure_claim_ids.*' => ['integer', 'distinct', 'exists:location_structure_claims,id'],
        ]);

        $locationId = $validated['location_id'] ?? null;
        $proposalId = $validated['location_proposal_id'] ?? null;
        $referenceSettlementExternalId = $validated['reference_settlement_external_id'] ?? null;
        $structuralClaims = LocationStructureClaim::query()->whereIn('id', $validated['location_structure_claim_ids'] ?? [])->get()->all();

        $hasLocation = $locationId !== null;
        $hasProposal = $proposalId !== null;
        $hasReferenceSettlement = $referenceSettlementExternalId !== null;
        if ((! $hasLocation && ! $hasProposal && ! $hasReferenceSettlement)
            || ($hasLocation && ($hasProposal || $hasReferenceSettlement))) {
            throw ValidationException::withMessages([
                'location_id' => 'لطفاً یک مسیر معتبر محل سکونت را انتخاب کنید.',
            ]);
        }

        if ($referenceSettlementExternalId !== null) {
            if (! (bool) config('iran_settlement_catalog.enabled', false)
                || ! (bool) config('iran_settlement_catalog.claims_enabled', false)) {
                throw ValidationException::withMessages([
                    'reference_settlement_external_id' => 'انتخاب آبادی مرجع در حال حاضر فعال نیست.',
                ]);
            }
            if ($structuralClaims !== []) {
                throw ValidationException::withMessages([
                    'location_structure_claim_ids' => 'ادعاهای ساختاری را نمی‌توان همزمان با آبادی مرجع ثبت کرد.',
                ]);
            }

            [$settlementClaim, $settlementProposal] = DB::transaction(function () use (
                $user,
                $proposalId,
                $referenceSettlementExternalId,
                $settlementAnchorResolver,
                $residenceService,
            ): array {
                $settlement = ReferenceSettlement::query()
                    ->where('source', 'IranCountryDivisions/geo_1404')
                    ->where('dataset_version', 'v2')
                    ->where('external_id', $referenceSettlementExternalId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $claimable = (
                    in_array($settlement->classification, ['unverified_settlement', 'needs_review'], true)
                    && $settlement->residential_eligibility === 'unverified'
                ) || (
                    $settlement->classification === 'verified_residential_village'
                    && $settlement->residential_eligibility === 'verified'
                );
                if (! $claimable || $settlement->governance_authorized || $settlement->operational_promotion_allowed) {
                    throw ValidationException::withMessages([
                        'reference_settlement_external_id' => 'این آبادی در وضعیت قابل انتخاب برای ثبت سکونت نیست.',
                    ]);
                }

                $anchor = $settlementAnchorResolver->resolve($settlement);
                $claim = ReferenceSettlementResidenceClaim::query()->firstOrCreate(
                    ['reference_settlement_id' => $settlement->id, 'user_id' => $user->id],
                    [
                        'status' => $settlement->residential_eligibility === 'verified' ? 'residential_evidence_verified' : 'pending',
                        'submitted_at' => now(),
                    ],
                );

                $proposal = null;
                if ($proposalId !== null) {
                    $proposal = LocationProposal::query()->with('type')->whereKey($proposalId)->lockForUpdate()->first();
                    if ($proposal === null
                        || ! in_array($proposal->status, [LocationProposalStatus::Pending, LocationProposalStatus::ReadyForReview, LocationProposalStatus::NeedsEvidence], true)
                        || (int) $proposal->parent_reference_settlement_id !== (int) $settlement->id
                        || $proposal->type?->key !== 'neighborhood') {
                        throw ValidationException::withMessages([
                            'location_proposal_id' => 'محلهٔ انتخاب‌شده متعلق به همین آبادی مرجع نیست.',
                        ]);
                    }
                }

                $current = $user->locationRelationships()
                    ->where('relationship_type', 'primary_residence')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->latest('started_at')
                    ->latest('id')
                    ->first();

                if ($current === null) {
                    $residenceService->setInitialPrimaryResidence($user, $anchor, [
                        'source' => 'profile_reference_settlement_anchor',
                        'reference_settlement_external_id' => $settlement->external_id,
                    ]);
                } elseif ((int) $current->location_id !== (int) $anchor->id) {
                    $reanchored = $residenceService->reanchorPrimaryResidenceIfVerifiedReferenceEquivalent(
                        $user,
                        $anchor,
                        [
                            'reference_settlement_external_id' => $settlement->external_id,
                            'source' => 'profile_reference_settlement_anchor',
                        ],
                    );
                    if ($reanchored === null) {
                        $residenceService->transferPrimaryResidence(
                            $user,
                            $anchor,
                            $user,
                            'profile_reference_settlement_anchor',
                            false,
                        );
                    }
                } else {
                    $residenceService->refreshPrimaryResidenceStructuralClaims($user, $anchor, []);
                }

                if ($proposal !== null) {
                    $residenceService->setPendingReferenceSettlementProposalIntent(
                        $user,
                        $claim,
                        $proposal,
                        $anchor,
                        ['source' => 'profile_reference_settlement_neighborhood'],
                    );
                } else {
                    $residenceService->setPendingReferenceSettlementIntent(
                        $user,
                        $claim,
                        $anchor,
                        ['source' => 'profile_reference_settlement'],
                    );
                }

                return [$claim, $proposal];
            });

            if ((bool) config('location-governance.groups_enabled', false)) {
                app(CanonicalGroupMembershipReconciler::class)->reconcile($user->fresh());
                $pendingGroups = app(\App\Services\Groups\PendingLocationGroupRequestService::class);
                if ($settlementProposal !== null) {
                    $pendingGroups->syncForReferenceSettlementProposal($user->fresh(), $settlementClaim, $settlementProposal);
                } else {
                    $pendingGroups->syncForReferenceSettlementClaim($user->fresh(), $settlementClaim);
                }
            }

            $profileCompletionService->maybeAward($user->fresh());

            return back()->with(
                'success',
                $settlementProposal !== null
                    ? 'آبادی و محلهٔ انتخابی شما ذخیره شدند و تا بررسی انسانی در وضعیت pending می‌مانند.'
                    : 'آبادی انتخابی شما به‌عنوان محل دقیق در انتظار بررسی ذخیره شد.'
            );
        }

        if ($locationId !== null) {
            $location = Location::query()->findOrFail($locationId);

            if ($location->status !== 'active' || ! $locationTreeResolver->residenceSelectionEndpointAllowed($location, $structuralClaims)) {
                throw ValidationException::withMessages([
                    'location_id' => 'لطفاً یک محل سکونت معتبر و قابل انتخاب را مشخص کنید.',
                ]);
            }

            $current = $user->locationRelationships()
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->latest('started_at')
                ->latest('id')
                ->first();

            if ($current === null) {
                $residenceService->setInitialPrimaryResidence($user, $location, [
                    'source' => 'profile_location_update',
                ], $structuralClaims);
            } elseif ((int) $current->location_id !== (int) $location->id) {
                $residenceService->transferPrimaryResidence(
                    $user,
                    $location,
                    $user,
                    'profile_location_update',
                    false,
                    $structuralClaims,
                );
            } else {
                $residenceService->refreshPrimaryResidenceStructuralClaims($user, $location, $structuralClaims);
                $residenceService->clearPendingResidenceIntent($user, 'approved_location_selected');
            }

            $profileCompletionService->maybeAward($user->fresh());

            return back()->with('success', 'محل سکونت اصلی شما با موفقیت به‌روزرسانی شد.');
        }

        DB::transaction(function () use (
            $user,
            $proposalId,
            $locationTreeResolver,
            $residenceService,
            $proposalPolicy,
            $structuralClaims,
        ): void {
            $proposal = LocationProposal::query()
                ->with(['parentLocation', 'parentProposal', 'type'])
                ->whereKey($proposalId)
                ->lockForUpdate()
                ->first();

            if ($proposal === null || ! in_array($proposal->status, [
                LocationProposalStatus::Pending,
                LocationProposalStatus::ReadyForReview,
                LocationProposalStatus::NeedsEvidence,
            ], true)) {
                throw ValidationException::withMessages([
                    'location_proposal_id' => 'این پیشنهاد مکان دیگر در وضعیت قابل انتخاب نیست.',
                ]);
            }

            $anchor = $proposal->nearestCanonicalParent();
            $type = $proposal->type;
            $parentProposal = $proposal->parentProposal;
            $canonicalStructuralClaims = array_values(array_filter(
                $structuralClaims,
                fn (LocationStructureClaim $claim): bool => $claim->location_id !== null,
            ));
            $proposalStructuralClaims = array_values(array_filter(
                $structuralClaims,
                fn (LocationStructureClaim $claim): bool => $claim->location_proposal_id !== null,
            ));
            $proposalPathAllowed = $proposal->parent_location_id !== null
                ? ($anchor !== null && $type !== null && $proposalPolicy->allowsForResidence($anchor, $type, $canonicalStructuralClaims))
                : ($parentProposal !== null && $type !== null && $proposalPolicy->allowsProposalParentForResidence($parentProposal, $type, $structuralClaims));

            if (
                $anchor === null
                || $type === null
                || $anchor->status !== 'active'
                || ! $proposalPathAllowed
            ) {
                throw ValidationException::withMessages([
                    'location_proposal_id' => 'پیشنهاد مکان انتخاب‌شده با مسیر معتبر محل سکونت سازگار نیست.',
                ]);
            }

            $current = $user->locationRelationships()
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->latest('started_at')
                ->latest('id')
                ->first();

            if ($current === null) {
                $residenceService->setInitialPrimaryResidence($user, $anchor, [
                    'source' => 'profile_pending_residence_anchor',
                    'location_proposal_id' => $proposal->id,
                ], $canonicalStructuralClaims);
            } elseif ((int) $current->location_id !== (int) $anchor->id) {
                $residenceService->transferPrimaryResidence(
                    $user,
                    $anchor,
                    $user,
                    'profile_pending_residence_anchor',
                    false,
                    $canonicalStructuralClaims,
                );
            } else {
                $residenceService->refreshPrimaryResidenceStructuralClaims($user, $anchor, $canonicalStructuralClaims);
            }

            $residenceService->setPendingResidenceIntent($user, $proposal, [
                'source' => 'profile_location_update',
            ], $proposalStructuralClaims);
        });

        $profileCompletionService->maybeAward($user->fresh());

        return back()->with(
            'success',
            'محل دقیق پیشنهادی شما ثبت شد و در انتظار بررسی است. تا زمان تأیید، حوزه رسمی شما بر اساس مکان تأییدشده فعلی محاسبه می‌شود.'
        );
    }
}
