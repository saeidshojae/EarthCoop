<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Profile\ProfileController;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
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
    ): RedirectResponse {
        if (! (bool) config('location-governance.registration_enabled')) {
            return app(ProfileController::class)->updateAddress($request);
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_proposal_id' => ['nullable', 'integer', 'exists:location_proposals,id'],
            'location_structure_claim_ids' => ['nullable', 'array'],
            'location_structure_claim_ids.*' => ['integer', 'distinct', 'exists:location_structure_claims,id'],
        ]);

        $locationId = $validated['location_id'] ?? null;
        $proposalId = $validated['location_proposal_id'] ?? null;
        $structuralClaims = LocationStructureClaim::query()->whereIn('id', $validated['location_structure_claim_ids'] ?? [])->get()->all();

        if (($locationId === null) === ($proposalId === null)) {
            throw ValidationException::withMessages([
                'location_id' => 'لطفاً دقیقاً یک محل تأییدشده یا یک پیشنهاد مکان در انتظار بررسی را انتخاب کنید.',
            ]);
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
                    $canonicalStructuralClaims,
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
