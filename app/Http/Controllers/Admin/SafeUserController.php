<?php

namespace App\Http\Controllers\Admin;

use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\LocationProposal;
use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Modules\NajmBahar\Services\MembershipRemovalService;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\LocationGovernance\IranV2RuntimeState;
use App\Services\Users\UserManagementService;
use Illuminate\Http\Request;

class SafeUserController extends UserController
{
    public function __construct(
        private readonly MembershipRemovalService $membershipRemoval,
        private readonly UserManagementService $userManagement,
        private readonly CanonicalGroupMembershipReconciler $canonicalGroupMembershipReconciler,
    ) {
    }

    public function edit(User $user)
    {
        if (! (bool) config('location-governance.registration_enabled')) {
            return parent::edit($user);
        }

        $primaryResidence = $user->locationRelationships()
            ->where('relationship_type', 'primary_residence')->whereNull('ended_at')->with('location')->latest('started_at')->latest('id')->first();
        $pendingResidenceIntent = $user->pendingResidenceIntents()
            ->where('status', 'pending')
            ->with(['locationProposal.type', 'referenceSettlementResidenceClaim.settlement'])
            ->latest('id')->first();
        $residenceHydrationPath = $this->residenceHydrationPath($primaryResidence?->location, $pendingResidenceIntent);
        $referenceSettlementProposalPath = $this->referenceSettlementProposalPath($pendingResidenceIntent);

        return view('admin.user.edit_canonical', compact(
            'user',
            'primaryResidence',
            'pendingResidenceIntent',
            'residenceHydrationPath',
            'referenceSettlementProposalPath',
        ));
    }

    public function update(Request $request, User $user)
    {
        $requestedStatus = $request->input('status');

        // Preserve the current lifecycle state while the legacy profile updater
        // handles non-lifecycle fields. Status changes are applied only through
        // the canonical UserManagementService below.
        if ($requestedStatus !== null) {
            $currentStatus = (string) $user->status;
            if (! in_array($currentStatus, ['active', 'inactive', 'suspended'], true)) {
                $currentStatus = 'active';
            }
            $request->merge(['status' => $currentStatus]);
        }

        $response = parent::update($request, $user);

        // Admin profile edits may change canonical membership inputs such as
        // birth date or gender. Reconciliation is idempotent and remains dark
        // until Stage C group activation, so every successful admin profile
        // update can safely converge the user's canonical memberships here.
        $freshUser = $user->refresh();
        if (! session()->has('error')
            && (bool) config('location-governance.groups_enabled', false)) {
            $this->canonicalGroupMembershipReconciler->reconcile($freshUser);
        }

        if ($requestedStatus !== null) {
            $result = $this->userManagement->setStatus($freshUser, (string) $requestedStatus);
            if (! (bool) ($result['success'] ?? false)) {
                return back()->with('error', 'تغییر وضعیت این هویت مجاز نیست');
            }
        }

        return $response;
    }

    public function destroy(User $user)
    {
        $this->membershipRemoval->remove($user->id, [
            'source' => 'admin.users.destroy',
            'actor_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'عضویت کاربر با حفظ دارایی‌ها و سوابق مالی خاتمه یافت');
    }

    public function updateStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $result = $this->userManagement->setStatus($user, (string) $validated['status']);
        if (! (bool) ($result['success'] ?? false)) {
            return back()->with('error', 'تغییر وضعیت این هویت مجاز نیست');
        }

        return back()->with('success', 'وضعیت کاربر با موفقیت تغییر کرد');
    }

    public function bulkAction(Request $request)
    {
        $action = (string) $request->input('action');

        if (in_array($action, ['activate', 'deactivate', 'suspend'], true)) {
            $validated = $request->validate([
                'action' => 'required|in:activate,deactivate,suspend',
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
            ]);

            $status = match ($validated['action']) {
                'activate' => 'active',
                'deactivate' => 'inactive',
                'suspend' => 'suspended',
            };

            $changed = 0;
            $protected = 0;
            foreach (User::query()->whereIn('id', $validated['user_ids'])->get() as $user) {
                $result = $this->userManagement->setStatus($user, $status);
                if ((bool) ($result['success'] ?? false)) {
                    $changed++;
                } else {
                    $protected++;
                }
            }

            $message = $changed . ' کاربر بروزرسانی شدند';
            if ($protected > 0) {
                $message .= '؛ ' . $protected . ' هویت محافظت‌شده بدون تغییر باقی ماند';
            }

            return back()->with('success', $message);
        }

        if ($action !== 'delete') {
            return parent::bulkAction($request);
        }

        $validated = $request->validate([
            'action' => 'required|in:delete',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        foreach (array_values(array_unique(array_map('intval', $validated['user_ids']))) as $userId) {
            $this->membershipRemoval->remove($userId, [
                'source' => 'admin.users.bulkAction',
                'actor_user_id' => auth()->id(),
            ]);
        }

        return back()->with(
            'success',
            count($validated['user_ids']) . ' عضویت با حفظ دارایی‌ها و سوابق مالی خاتمه یافت'
        );
    }
    /** @return array<int, string> */
    private function residenceHydrationPath(?Location $location, ?PendingResidenceIntent $intent): array
    {
        $referenceAnchor = $this->referenceSettlementAnchor($intent);
        if ($referenceAnchor instanceof Location) {
            return $this->canonicalLocationPath($referenceAnchor);
        }

        $proposal = $intent?->locationProposal;
        if (! $proposal instanceof LocationProposal) return $this->canonicalLocationPath($location);
        $proposalPath = []; $cursor = $proposal; $visited = []; $anchor = null;
        while ($cursor !== null) {
            if (isset($visited[$cursor->id])) return $this->canonicalLocationPath($location);
            $visited[$cursor->id] = true; array_unshift($proposalPath, 'proposal:'.$cursor->id);
            if ($cursor->parent_location_id !== null) { $anchor = $cursor->parentLocation()->first(); break; }
            $cursor = $cursor->parentProposal()->first();
        }
        return $anchor instanceof Location ? [...$this->canonicalLocationPath($anchor), ...$proposalPath] : $this->canonicalLocationPath($location);
    }

    private function referenceSettlementAnchor(?PendingResidenceIntent $intent): ?Location
    {
        $settlement = $intent?->referenceSettlementResidenceClaim?->settlement;
        $parentExternalId = $settlement?->parent_external_id;
        if (! is_string($parentExternalId) || $parentExternalId === '') {
            return null;
        }

        return LocationExternalId::query()
            ->with('location')
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v2')
            ->where('external_id', $parentExternalId)
            ->first()?->location;
    }

    /** @return array<int, string> */
    private function canonicalLocationPath(?Location $location): array
    {
        if (! $location instanceof Location) return [];
        $location = $this->preferredHydrationLocation($location);
        $path = []; $cursor = $location; $visited = []; $root = null;
        while ($cursor !== null) {
            if (isset($visited[$cursor->id])) return [];
            $visited[$cursor->id] = true; $root = $cursor; array_unshift($path, 'location:'.$cursor->id); $cursor = $cursor->parent()->first();
        }
        if ($root instanceof Location) {
            $country = $root->governanceAreas()->official()->active()->where('governance_type', 'country')->first();
            $continent = $country?->parent()->official()->active()->where('governance_type', 'continent')->first();
            if ($continent !== null) array_unshift($path, 'governance:'.$continent->id);
        }
        return $path;
    }

    /** @return array<int, int> */
    private function referenceSettlementProposalPath(?PendingResidenceIntent $intent): array
    {
        if ($intent?->reference_settlement_residence_claim_id === null || ! $intent?->locationProposal instanceof LocationProposal) {
            return [];
        }

        $path = [];
        $cursor = $intent->locationProposal;
        $visited = [];
        while ($cursor !== null) {
            if (isset($visited[$cursor->id])) return [];
            $visited[$cursor->id] = true;
            array_unshift($path, (int) $cursor->id);
            if ($cursor->parent_reference_settlement_id !== null) return $path;
            $cursor = $cursor->parentProposal()->first();
        }

        return [];
    }

    private function preferredHydrationLocation(Location $location): Location
    {
        if ($location->country_code !== 'IR' || ! app(IranV2RuntimeState::class)->isActive()) return $location;

        $alreadyV2 = LocationExternalId::query()
            ->where('location_id', $location->id)
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v2')
            ->exists();
        if ($alreadyV2) return $location;

        $v1ExternalId = LocationExternalId::query()
            ->where('location_id', $location->id)
            ->where('source', config('iran_v1_v2_crosswalk.source', 'earthcoop-reference'))
            ->where('dataset_version', config('iran_v1_v2_crosswalk.v1_dataset_version', 'v1'))
            ->value('external_id');
        if (! is_string($v1ExternalId) || $v1ExternalId === '') return $location;

        $mapping = config('iran_v1_v2_crosswalk.mappings.'.$v1ExternalId);
        if (! is_array($mapping) || ($mapping['status'] ?? null) !== 'verified_identity') return $location;

        $v2 = LocationExternalId::query()
            ->with('location')
            ->where('source', config('iran_v1_v2_crosswalk.source', 'earthcoop-reference'))
            ->where('dataset_version', config('iran_v1_v2_crosswalk.v2_dataset_version', 'v2'))
            ->where('external_id', (string) ($mapping['v2'] ?? ''))
            ->first()?->location;

        return $v2 instanceof Location && $v2->status === 'active' ? $v2 : $location;
    }

}
