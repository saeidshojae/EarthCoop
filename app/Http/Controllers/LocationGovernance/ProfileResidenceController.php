<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Profile\ProfileController;
use App\Models\Location;
use App\Services\LocationGovernance\LocationTreeResolver;
use App\Services\LocationGovernance\ResidenceService;
use App\Services\ProfileCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ProfileResidenceController extends Controller
{
    public function update(
        Request $request,
        LocationTreeResolver $locationTreeResolver,
        ResidenceService $residenceService,
        ProfileCompletionService $profileCompletionService,
    ): RedirectResponse {
        if (! (bool) config('location-governance.registration_enabled')) {
            return app(ProfileController::class)->updateAddress($request);
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
        ]);

        $location = Location::query()->findOrFail($validated['location_id']);

        if ($location->status !== 'active' || ! $locationTreeResolver->residenceEndpointAllowed($location)) {
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
            ]);
        } elseif ((int) $current->location_id !== (int) $location->id) {
            $residenceService->transferPrimaryResidence(
                $user,
                $location,
                $user,
                'profile_location_update',
            );
        }

        $profileCompletionService->maybeAward($user->fresh());

        return back()->with('success', 'محل سکونت اصلی شما با موفقیت به‌روزرسانی شد.');
    }
}
