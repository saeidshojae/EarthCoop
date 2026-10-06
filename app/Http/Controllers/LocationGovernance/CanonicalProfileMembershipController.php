<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Profile\ProfileController;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\ProfileCompletionService;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ValueError;

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

        $user = $request->user();
        abort_unless($user !== null, 401);

        $birthDate = $request->input('birth_date');
        $canonicalBirthDate = null;

        if ($birthDate !== null) {
            $context = app(TemporalContextResolver::class)->forUser($user);

            try {
                if (is_array($birthDate)) {
                    if (count($birthDate) < 3
                        || $birthDate[0] === ''
                        || $birthDate[1] === ''
                        || $birthDate[2] === '') {
                        throw new InvalidArgumentException('Birth date parts are incomplete.');
                    }

                    $canonicalBirthDate = app(TemporalService::class)->parseDateParts(
                        (int) $birthDate[0],
                        (int) $birthDate[1],
                        (int) $birthDate[2],
                        $context,
                    );
                } elseif (is_string($birthDate)) {
                    $canonicalBirthDate = app(TemporalService::class)->parseDate(
                        trim($birthDate),
                        $context,
                    );
                } else {
                    throw new InvalidArgumentException('Birth date must be a localized date string or legacy date-parts array.');
                }
            } catch (InvalidArgumentException|ValueError) {
                throw ValidationException::withMessages([
                    'birth_date' => __('validation.date', ['attribute' => 'birth_date']),
                ]);
            }

            // Registration cutover owns the canonical birth-date value even while
            // Stage C groups are dark. Remove it from the mature profile request so
            // the legacy age-group detach/materialize block can never run here.
            // The canonical value is persisted below after mature profile rules pass.
            $request->request->remove('birth_date');
        }

        $response = app(ProfileController::class)->updateGeneral($request);

        // Legacy validation exceptions never reach this point. Explicit mature
        // business-rule errors are flashed and must not be followed by canonical
        // mutation of the withheld birth date.
        if (session()->has('error')) {
            return $response;
        }

        DB::transaction(function () use ($user, $birthDate, $canonicalBirthDate, $reconciler): void {
            if ($birthDate !== null && $canonicalBirthDate !== null) {
                $user->forceFill([
                    'birth_date' => $canonicalBirthDate->toCanonical(),
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
