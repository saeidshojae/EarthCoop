<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\User;
use App\Services\LocationGovernance\LocationTreeResolver;
use App\Services\LocationGovernance\ResidenceService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class UserResidenceController extends Controller
{
    public function __construct(
        private readonly ResidenceService $residenceService,
        private readonly LocationTreeResolver $locationTreeResolver,
    ) {
    }

    public function update(Request $request, User $user)
    {
        abort_unless((bool) config('location-governance.registration_enabled', false), 404);

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_proposal_id' => ['nullable', 'integer', 'exists:location_proposals,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $locationId = $validated['location_id'] ?? null;
        $proposalId = $validated['location_proposal_id'] ?? null;
        $reason = trim((string) ($validated['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'دلیل تغییر محل سکونت الزامی است.',
            ]);
        }

        if (($locationId === null && $proposalId === null)
            || ($locationId !== null && $proposalId !== null)) {
            throw ValidationException::withMessages([
                'location_id' => 'دقیقاً یک مکان تأییدشده یا یک مکان پیشنهادی را انتخاب کنید.',
            ]);
        }

        $metadata = [
            'source' => 'admin_user_residence',
            'actor_user_id' => (int) $request->user()->id,
            'change_reason' => $reason,
            'reason' => $reason,
        ];

        if ($locationId !== null) {
            $this->updateApprovedResidence($user, (int) $locationId, $metadata);
        } else {
            $this->updatePendingResidence($user, (int) $proposalId, $metadata);
        }

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'محل سکونت کاربر با موفقیت بروزرسانی شد.');
    }

    private function updateApprovedResidence(User $user, int $locationId, array $metadata): void
    {
        $location = Location::query()->findOrFail($locationId);

        if ($location->status !== 'active'
            || ! $this->locationTreeResolver->residenceEndpointAllowed($location)) {
            throw ValidationException::withMessages([
                'location_id' => 'این مکان برای ثبت محل سکونت معتبر نیست.',
            ]);
        }

        try {
            $current = $this->residenceService->currentPrimaryResidence($user);

            if ($current !== null && (int) $current->location_id === $location->id) {
                $this->residenceService->clearPendingResidenceIntent(
                    $user,
                    'approved_location_selected_by_admin'
                );

                return;
            }

            if ($current === null) {
                $this->residenceService->setInitialPrimaryResidence($user, $location, $metadata);

                return;
            }

            $this->residenceService->transferPrimaryResidence($user, $location, $metadata);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'location_id' => $exception->getMessage(),
            ]);
        }
    }

    private function updatePendingResidence(User $user, int $proposalId, array $metadata): void
    {
        $proposal = LocationProposal::query()->findOrFail($proposalId);
        $openStatuses = [
            LocationProposalStatus::Pending->value,
            LocationProposalStatus::ReadyForReview->value,
            LocationProposalStatus::NeedsEvidence->value,
        ];

        if (! in_array((string) $proposal->status, $openStatuses, true)) {
            throw ValidationException::withMessages([
                'location_proposal_id' => 'این پیشنهاد دیگر برای انتخاب محل سکونت باز نیست.',
            ]);
        }

        $anchor = $proposal->parentLocation;
        if ($anchor === null
            || $anchor->status !== 'active'
            || ! $this->locationTreeResolver->residenceEndpointAllowed($anchor)) {
            throw ValidationException::withMessages([
                'location_proposal_id' => 'مکان مبنای این پیشنهاد برای سکونت معتبر نیست.',
            ]);
        }

        try {
            $current = $this->residenceService->currentPrimaryResidence($user);
            $anchorMetadata = array_merge($metadata, [
                'source' => 'admin_pending_residence_anchor',
            ]);

            if ($current === null) {
                $this->residenceService->setInitialPrimaryResidence($user, $anchor, $anchorMetadata);
            } elseif ((int) $current->location_id !== $anchor->id) {
                $this->residenceService->transferPrimaryResidence($user, $anchor, $anchorMetadata);
            }

            $this->residenceService->setPendingResidenceIntent($user, $proposal, $metadata);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'location_proposal_id' => $exception->getMessage(),
            ]);
        }
    }
}
