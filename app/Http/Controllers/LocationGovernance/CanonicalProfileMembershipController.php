<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Profile\ProfileController;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\ProfileCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

final class CanonicalProfileMembershipController extends Controller
{
    public function updateExperience(
        Request $request,
        CanonicalGroupMembershipReconciler $reconciler,
    ): RedirectResponse {
        if (! (bool) config('location-governance.registration_enabled', false)) {
            return app(ProfileController::class)->updateExperience($request);
        }

        $validated = $request->validate([
            'occupational_fields' => 'required|array',
            'occupational_fields.*' => 'exists:occupational_fields,id',
            'experience_fields' => 'required|array',
            'experience_fields.*' => 'exists:experience_fields,id',
        ]);

        $user = $request->user();
        abort_unless($user !== null, 401);

        DB::transaction(function () use ($user, $validated, $reconciler): void {
            $user->specialties()->sync($validated['occupational_fields']);
            $user->experiences()->sync($validated['experience_fields']);

            if ((bool) config('location-governance.groups_enabled', false)) {
                $reconciler->reconcile($user->fresh());
            }
        });

        app(ProfileCompletionService::class)->maybeAward($user->fresh());

        return redirect()->route('profile.edit')
            ->with('success', 'زمینه‌های صنفی و تجربی با موفقیت به‌روزرسانی شدند.');
    }

    public function updateGeneral(
        Request $request,
        CanonicalGroupMembershipReconciler $reconciler,
    ): RedirectResponse {
        if (! (bool) config('location-governance.registration_enabled', false)) {
            return app(ProfileController::class)->updateGeneral($request);
        }

        $birthDate = $request->input('birth_date');
        if ($birthDate !== null) {
            validator(
                ['birth_date' => $birthDate],
                ['birth_date' => 'nullable|array|min:3'],
            )->validate();

            // Registration cutover owns the canonical birth-date value even while
            // Stage C groups are dark. Remove it from the mature legacy request so
            // the old age-group detach/materialize block can never run in canonical
            // registration mode. The value is persisted below, while membership
            // reconciliation remains independently gated by groups_enabled.
            $request->request->remove('birth_date');
        }

        $response = app(ProfileController::class)->updateGeneral($request);

        // Legacy validation exceptions never reach this point. Explicit mature
        // business-rule errors are flashed and must not be followed by canonical
        // mutation of the withheld birth date.
        if (session()->has('error')) {
            return $response;
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        DB::transaction(function () use ($user, $birthDate, $reconciler): void {
            if (is_array($birthDate) && count($birthDate) >= 3) {
                $user->forceFill([
                    'birth_date' => (new Jalalian(
                        (int) $birthDate[2],
                        (int) $birthDate[1],
                        (int) $birthDate[0],
                    ))->toCarbon(),
                ])->save();
            }

            // Gender is saved by the mature controller above; age is saved here.
            // Membership writes are dark until Stage C group activation, so profile
            // data can move to the canonical runtime without creating legacy groups.
            if ((bool) config('location-governance.groups_enabled', false)) {
                $reconciler->reconcile($user->fresh());
            }
        });

        app(ProfileCompletionService::class)->maybeAward($user->fresh());

        return $response;
    }
}
