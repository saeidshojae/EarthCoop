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
            try {
                return app(ProfileController::class)->updateGeneral($request);
            } catch (ValidationException $e) {
                return redirect()->route('profile.edit')
                    ->withErrors($e->errors())
                    ->withInput();
            }
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $identityPolicyErrors = $this->identityPolicyErrors($request, $user);
        if ($identityPolicyErrors !== []) {
            return redirect()->route('profile.edit')
                ->withErrors($identityPolicyErrors)
                ->withInput();
        }

        $birthDate = $request->input('birth_date');
        $canonicalBirthDate = null;
        $originalBirthDate = (string) $user->getRawOriginal('birth_date');

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

            if ($user->hasUsedIdentityEdit()
                && $canonicalBirthDate !== null
                && $canonicalBirthDate->toCanonical() !== substr($originalBirthDate, 0, 10)) {
                throw ValidationException::withMessages([
                    'birth_date' => 'تاریخ تولد قبلاً یک‌بار ویرایش شده و دیگر قابل تغییر نیست.',
                ]);
            }

            // Registration cutover owns the canonical birth-date value even while
            // Stage C groups are dark. Remove it from the mature profile request so
            // the legacy age-group detach/materialize block can never run here.
            // The canonical value is persisted below after mature profile rules pass.
            $request->request->remove('birth_date');
        }

        try {
            $response = app(ProfileController::class)->updateGeneral($request);
        } catch (ValidationException $e) {
            return redirect()->route('profile.edit')
                ->withErrors($e->errors())
                ->withInput();
        }

        // Legacy validation exceptions never reach this point. Explicit mature
        // business-rule errors are flashed and must not be followed by canonical
        // mutation of the withheld birth date.
        if (session()->has('error')) {
            return $response;
        }

        DB::transaction(function () use ($user, $birthDate, $canonicalBirthDate, $originalBirthDate, $reconciler): void {
            if ($birthDate !== null && $canonicalBirthDate !== null) {
                $newBirthDate = $canonicalBirthDate->toCanonical();
                $birthDateChanged = $newBirthDate !== substr($originalBirthDate, 0, 10);

                $changes = ['birth_date' => $newBirthDate];
                if ($birthDateChanged && ! $user->hasUsedIdentityEdit()) {
                    $changes['identity_edit_used_at'] = now();
                    $changes['edited'] = 1;
                }

                $user->forceFill($changes)->save();
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

    /**
     * Enforce account identity invariants at the canonical boundary so the
     * behaviour is identical regardless of legacy/canonical runtime fallback.
     *
     * @return array<string,string>
     */
    private function identityPolicyErrors(Request $request, $user): array
    {
        $errors = [];

        if ($request->has('email') && (string) $request->input('email') !== (string) $user->email) {
            $errors['email'] = 'ایمیل حساب قابل تغییر نیست.';
        }
        if ($request->has('national_id') && (string) $request->input('national_id') !== (string) $user->national_id) {
            $errors['national_id'] = 'کد ملی پس از ثبت اولیه قابل تغییر نیست.';
        }

        $identityFields = ['first_name', 'last_name', 'gender', 'phone', 'country_code'];
        if ($user->hasUsedIdentityEdit()) {
            foreach ($identityFields as $field) {
                if (! $request->exists($field)) {
                    continue;
                }

                $incoming = $request->input($field);
                $current = $field === 'country_code'
                    ? ($user->phone_country_code ?: '+98')
                    : $user->{$field};

                if ((string) $incoming !== (string) $current) {
                    $errors[$field] = 'این بخش از اطلاعات هویتی قبلاً یک‌بار ویرایش شده و دیگر قابل تغییر نیست.';
                }
            }
        }

        if (($request->exists('phone') || $request->exists('country_code')) && ! isset($errors['phone'])) {
            $candidateCode = (string) $request->input('country_code', $user->phone_country_code ?: '+98');
            $candidatePhone = (string) $request->input('phone', $user->phone);

            $collision = \App\Models\User::query()
                ->where('phone_country_code', $candidateCode)
                ->where('phone', $candidatePhone)
                ->whereKeyNot($user->id)
                ->exists();

            if ($collision) {
                $errors['phone'] = 'این شماره تلفن با همین کد کشور قبلاً ثبت شده است.';
            }
        }

        return $errors;
    }

}
