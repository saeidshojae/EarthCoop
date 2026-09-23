<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\User;
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
    ): RedirectResponse {
        abort_unless((bool) config('location-governance.registration_enabled', false), 404);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_proposal_id' => ['nullable', 'integer', 'exists:location_proposals,id'],
            'location_structure_claim_ids' => ['nullable', 'array'],
            'location_structure_claim_ids.*' => ['integer', 'distinct', 'exists:location_structure_claims,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $locationId = $validated['location_id'] ?? null;
        $proposalId = $validated['location_proposal_id'] ?? null;
        $structuralClaims = LocationStructureClaim::query()->whereIn('id', $validated['location_structure_claim_ids'] ?? [])->get()->all();
        $reason = trim((string) ($validated['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'دلیل تغییر محل سکونت الزامی است.',
            ]);
        }

        if (($locationId === null) === ($proposalId === null)) {
            throw ValidationException::withMessages([
                'location_id' => 'دقیقاً یک مکان تأییدشده یا یک مکان پیشنهادی را انتخاب کنید.',
            ]);
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
                        $canonicalStructuralClaims,
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
