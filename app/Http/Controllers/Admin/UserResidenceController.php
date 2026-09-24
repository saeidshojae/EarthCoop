<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\LocationStructureClaim;
use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\LocationGovernance\IranSettlementAnchorResolver;
use App\Services\LocationGovernance\LocationProposalPolicy;
use App\Services\LocationGovernance\LocationTreeResolver;
use App\Services\LocationGovernance\ResidenceService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserResidenceController extends Controller
{
    public function update(
        Request $request,
        User $user,
        LocationTreeResolver $locationTreeResolver,
        ResidenceService $residenceService,
        LocationProposalPolicy $proposalPolicy,
        IranSettlementAnchorResolver $settlementAnchorResolver,
    ): RedirectResponse {
        abort_unless((bool) config('location-governance.registration_enabled', false), 404);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_proposal_id' => ['nullable', 'integer', 'exists:location_proposals,id'],
            'reference_settlement_external_id' => ['nullable', 'string', 'regex:/^IR-1404-[1-9][0-9]*$/D'],
            'location_structure_claim_ids' => ['nullable', 'array'],
            'location_structure_claim_ids.*' => ['integer', 'distinct', 'exists:location_structure_claims,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $locationId = $validated['location_id'] ?? null;
        $proposalId = $validated['location_proposal_id'] ?? null;
        $referenceSettlementExternalId = $validated['reference_settlement_external_id'] ?? null;
        $structuralClaims = LocationStructureClaim::query()->whereIn('id', $validated['location_structure_claim_ids'] ?? [])->get()->all();
        $reason = trim((string) ($validated['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'دلیل تغییر محل سکونت الزامی است.',
            ]);
        }

        $hasLocation = $locationId !== null;
        $hasProposal = $proposalId !== null;
        $hasReferenceSettlement = $referenceSettlementExternalId !== null;
        if ((! $hasLocation && ! $hasProposal && ! $hasReferenceSettlement)
            || ($hasLocation && ($hasProposal || $hasReferenceSettlement))) {
            throw ValidationException::withMessages([
                'location_id' => 'یک مسیر معتبر محل سکونت را انتخاب کنید.',
            ]);
        }

        if ($referenceSettlementExternalId !== null) {
            if (! (bool) config('iran_settlement_catalog.enabled', false)
                || ! (bool) config('iran_settlement_catalog.claims_enabled', false)) {
                throw ValidationException::withMessages([
                    'reference_settlement_external_id' => 'انتخاب آبادی مرجع در حال حاضر فعال نیست.',
                ]);
            }
            [$settlementClaim, $settlementProposal, $settlementStructuralClaims] = DB::transaction(function () use (
                $user,
                $actor,
                $proposalId,
                $referenceSettlementExternalId,
                $settlementAnchorResolver,
                $residenceService,
                $reason,
                $structuralClaims,
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
                        'reference_settlement_external_id' => 'این آبادی در وضعیت قابل انتخاب برای محل سکونت نیست.',
                    ]);
                }

                $referenceStructuralClaims = collect($structuralClaims)
                    ->filter(fn (LocationStructureClaim $claim): bool =>
                        (int) $claim->reference_settlement_id === (int) $settlement->id
                        && $claim->claim_type === 'no_neighborhood'
                        && in_array($claim->status, ['pending', 'ready_for_review', 'needs_evidence', 'approved'], true)
                    )->values();
                if ($referenceStructuralClaims->count() !== count($structuralClaims)) {
                    throw ValidationException::withMessages([
                        'location_structure_claim_ids' => 'اعلام ساختاری انتخاب‌شده متعلق به همین آبادی مرجع نیست.',
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
                    $proposal = LocationProposal::query()->with(['type', 'parentProposal.type'])
                        ->whereKey($proposalId)->lockForUpdate()->first();
                    if ($proposal === null
                        || ! in_array($proposal->status, [
                            LocationProposalStatus::Pending,
                            LocationProposalStatus::ReadyForReview,
                            LocationProposalStatus::NeedsEvidence,
                        ], true)) {
                        throw ValidationException::withMessages([
                            'location_proposal_id' => 'جزئیات انتخاب‌شده دیگر در وضعیت قابل استفاده نیست.',
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
                        'source' => 'admin_reference_settlement_anchor',
                        'actor_user_id' => $actor->id,
                        'reason' => $reason,
                        'reference_settlement_external_id' => $settlement->external_id,
                    ]);
                } elseif ((int) $current->location_id !== (int) $anchor->id) {
                    $reanchored = $residenceService->reanchorPrimaryResidenceIfVerifiedReferenceEquivalent(
                        $user,
                        $anchor,
                        [
                            'source' => 'admin_reference_settlement_anchor',
                            'actor_user_id' => $actor->id,
                            'reason' => $reason,
                            'reference_settlement_external_id' => $settlement->external_id,
                        ],
                    );
                    if ($reanchored === null) {
                        $residenceService->transferPrimaryResidence(
                            $user,
                            $anchor,
                            $actor,
                            $reason,
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
                        [
                            'source' => 'admin_reference_settlement_detail',
                            'actor_user_id' => $actor->id,
                            'reason' => $reason,
                            'structural_claim_ids' => $referenceStructuralClaims
                                ->pluck('id')->map(fn ($id) => (int) $id)->all(),
                        ],
                    );
                } else {
                    $residenceService->setPendingReferenceSettlementIntent(
                        $user,
                        $claim,
                        $anchor,
                        [
                            'source' => 'admin_reference_settlement',
                            'actor_user_id' => $actor->id,
                            'reason' => $reason,
                            'structural_claim_ids' => $referenceStructuralClaims
                                ->pluck('id')->map(fn ($id) => (int) $id)->all(),
                        ],
                    );
                }

                return [$claim, $proposal, $referenceStructuralClaims];
            });

            foreach ($settlementStructuralClaims as $structuralClaim) {
                app(\App\Services\LocationGovernance\LocationStructureClaimService::class)
                    ->recordCommittedSupport($structuralClaim, $user, [
                        'source' => 'admin_reference_settlement',
                        'actor_user_id' => $actor->id,
                        'reason' => $reason,
                        'reference_settlement_external_id' => $referenceSettlementExternalId,
                    ]);
            }

            if ((bool) config('location-governance.groups_enabled', false)) {
                app(CanonicalGroupMembershipReconciler::class)->reconcile($user->fresh());
                $pendingGroups = app(\App\Services\Groups\PendingLocationGroupRequestService::class);
                if ($settlementProposal !== null) {
                    $pendingGroups->syncForReferenceSettlementProposal($user->fresh(), $settlementClaim, $settlementProposal);
                } else {
                    $pendingGroups->syncForReferenceSettlementClaim($user->fresh(), $settlementClaim);
                }
            }

            return redirect()
                ->route('admin.users.edit', $user)
                ->with('success', 'محل دقیق کاربر بر اساس آبادی مرجع ثبت شد و جزئیات در انتظار بررسی باقی می‌مانند.');
        }

        if ($locationId !== null) {
            $location = Location::query()->findOrFail($locationId);

            if ($location->status !== 'active' || ! $locationTreeResolver->residenceSelectionEndpointAllowed($location, $structuralClaims)) {
                throw ValidationException::withMessages([
                    'location_id' => 'این مکان برای ثبت محل سکونت معتبر نیست.',
                ]);
            }

            try {
                $current = $user->locationRelationships()
                    ->where('relationship_type', 'primary_residence')
                    ->whereNull('ended_at')
                    ->latest('started_at')
                    ->latest('id')
                    ->first();

                if ($current === null) {
                    $residenceService->setInitialPrimaryResidence($user, $location, [
                        'source' => 'admin_user_residence',
                        'actor_user_id' => $actor->id,
                        'reason' => $reason,
                    ], $structuralClaims);
                } elseif ((int) $current->location_id !== (int) $location->id) {
                    $residenceService->transferPrimaryResidence(
                        $user,
                        $location,
                        $actor,
                        $reason,
                        false,
                        $structuralClaims,
                    );
                } else {
                    $residenceService->refreshPrimaryResidenceStructuralClaims($user, $location, $structuralClaims);
                    $residenceService->clearPendingResidenceIntent($user, 'approved_location_selected_by_admin');
                }
            } catch (DomainException $exception) {
                throw ValidationException::withMessages([
                    'location_id' => $exception->getMessage(),
                ]);
            }

            return redirect()
                ->route('admin.users.edit', $user)
                ->with('success', 'محل سکونت کاربر با موفقیت به‌روزرسانی شد.');
        }

        DB::transaction(function () use (
            $user,
            $actor,
            $proposalId,
            $reason,
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
                    'location_proposal_id' => 'این پیشنهاد دیگر برای انتخاب محل سکونت باز نیست.',
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
                    'location_proposal_id' => 'مکان پیشنهادی با مسیر معتبر محل سکونت سازگار نیست.',
                ]);
            }

            try {
                $current = $user->locationRelationships()
                    ->where('relationship_type', 'primary_residence')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->latest('started_at')
                    ->latest('id')
                    ->first();

                if ($current === null) {
                    $residenceService->setInitialPrimaryResidence($user, $anchor, [
                        'source' => 'admin_pending_residence_anchor',
                        'actor_user_id' => $actor->id,
                        'reason' => $reason,
                        'location_proposal_id' => $proposal->id,
                    ], $canonicalStructuralClaims);
                } elseif ((int) $current->location_id !== (int) $anchor->id) {
                    $residenceService->transferPrimaryResidence(
                        $user,
                        $anchor,
                        $actor,
                        $reason,
                        false,
                        $canonicalStructuralClaims,
                    );
                } else {
                    $residenceService->refreshPrimaryResidenceStructuralClaims($user, $anchor, $canonicalStructuralClaims);
                }

                $residenceService->setPendingResidenceIntent($user, $proposal, [
                    'source' => 'admin_user_residence',
                    'actor_user_id' => $actor->id,
                    'reason' => $reason,
                ], $proposalStructuralClaims);
            } catch (DomainException $exception) {
                throw ValidationException::withMessages([
                    'location_proposal_id' => $exception->getMessage(),
                ]);
            }
        });

        return redirect()
            ->route('admin.users.edit', $user)
            ->with(
                'success',
                'جزئیات دقیق محل سکونت کاربر ثبت شد و تا زمان تأیید، حوزه رسمی بر مبنای مکان تأییدشده محاسبه می‌شود.'
            );
    }
}
