<?php

namespace App\Http\Controllers\Profile;

use App\Models\InvitationCode;
use App\Models\Setting;
use App\Models\User;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\ProfileCompletionService;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Formatting\DigitNormalizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ValueError;

final class CanonicalProfileController extends ProfileController
{
    public function updateExperience(Request $request)
    {
        if (! (bool) config('location-governance.registration_enabled', false)) {
            return parent::updateExperience($request);
        }

        $validated = $request->validate([
            'occupational_fields' => 'required|array',
            'occupational_fields.*' => 'exists:occupational_fields,id',
            'experience_fields' => 'required|array',
            'experience_fields.*' => 'exists:experience_fields,id',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($user, $validated): void {
            $user->occupationalFields()->sync($validated['occupational_fields']);
            $user->experienceFields()->sync($validated['experience_fields']);
            $user->unsetRelation('occupationalFields');
            $user->unsetRelation('experienceFields');

            if ((bool) config('location-governance.groups_enabled', false)) {
                app(CanonicalGroupMembershipReconciler::class)->reconcile($user->fresh());
            }
        });

        app(ProfileCompletionService::class)->maybeAward($user->fresh());

        return redirect()->route('profile.edit')
            ->with('success', 'زمینه‌های صنفی و تجربی با موفقیت به‌روزرسانی شدند.');
    }

    public function updateGeneral(Request $request)
    {
        if (! (bool) config('location-governance.registration_enabled', false)) {
            $this->bridgeLocalizedBirthDateToLegacyParts($request);

            return parent::updateGeneral($request);
        }

        $inputs = $request->validate([
            'first_name' => 'nullable|string|max:50|regex:/^[آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی\\s]+$/u',
            'last_name' => 'nullable|string|max:50|regex:/^[آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی\\s]+$/u',
            'birth_date' => 'nullable',
            'gender' => 'nullable|in:male,female',
            'national_id' => 'nullable|string|regex:/^\\d{10}$/|unique:users,national_id,'.Auth::id(),
            'phone' => 'nullable|regex:/^(0)?9\\d{9}$/|unique:users,phone,'.Auth::id(),
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4084',
            'document_names.*' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:4084',
            'biografie' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = User::findOrFail(Auth::id());

        if (isset($inputs['national_id']) && $inputs['national_id'] !== null
            && ! $this->isValidIranianNationalCode($inputs['national_id'])) {
            return back()->with('error', 'کد ملی وارد شده معتبر نیست')->withInput();
        }

        if ($request->has('remove_avatar') && $request->remove_avatar === '1') {
            if ($user->avatar) {
                $oldAvatarPath = public_path('images/users/avatars/'.$user->avatar);
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }
            $inputs['avatar'] = null;
        } elseif ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            if ($user->avatar) {
                $oldAvatarPath = public_path('images/users/avatars/'.$user->avatar);
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }

            $file = $request->file('avatar');
            $name = time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('images/users/avatars/'), $name);
            $inputs['avatar'] = $name;
        }

        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            $documentNames = $request->input('document_names', []);
            $existingDocuments = [];

            if ($user->documents) {
                $decoded = json_decode($user->documents, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $existingDocuments = $decoded;
                } else {
                    foreach (explode(',', $user->documents) as $existingFile) {
                        $existingFile = trim($existingFile);
                        if ($existingFile !== '') {
                            $existingDocuments[] = [
                                'filename' => $existingFile,
                                'name' => 'مدرک',
                                'type' => strtolower(pathinfo($existingFile, PATHINFO_EXTENSION)),
                            ];
                        }
                    }
                }
            }

            if (count($existingDocuments) + count($files) > 5) {
                return back()->with('error', 'شما می‌توانید حداکثر ۵ فایل داشته باشید. لطفاً ابتدا برخی از فایل‌های موجود را حذف کنید.')->withInput();
            }

            foreach ($files as $index => $file) {
                $name = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                $file->move(public_path('images/users/documents'), $name);
                $existingDocuments[] = [
                    'filename' => $name,
                    'name' => ! empty($documentNames[$index]) ? trim($documentNames[$index]) : 'مدرک',
                    'type' => strtolower($file->getClientOriginalExtension()),
                ];
            }

            $inputs['documents'] = json_encode($existingDocuments, JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('birth_date', $inputs)) {
            $inputs['birth_date'] = $this->canonicalBirthDate($inputs['birth_date']);
        }

        DB::transaction(function () use ($user, $inputs): void {
            $user->update($inputs);

            if ($user->first_name !== null
                && $user->last_name !== null
                && $user->gender !== null
                && $user->national_id !== null
                && $user->phone !== null) {
                $user->status = 1;
                $user->edited = 1;
                $user->save();
            }

            if ((bool) config('location-governance.groups_enabled', false)) {
                app(CanonicalGroupMembershipReconciler::class)->reconcile($user->fresh());
            }
        });

        app(ProfileCompletionService::class)->maybeAward($user->fresh());

        return back()->with('success', 'پروفایل با موفقیت ویرایش شد');
    }

    public function sendInvitation(Request $request)
    {
        $request->validate([
            'invite_email' => 'required|email',
        ]);

        $setting = Setting::find(1);
        $code = InvitationCode::create([
            'code' => Str::random(10),
            'user_id' => auth()->id(),
            'expire_at' => Carbon::now()->addHours(intval($setting->expire_invation_time ?? 72)),
        ]);

        app(CommunicationDispatcher::class)->dispatchExternal(
            'membership.member_invitation',
            [
                'type' => 'member_invitation_code',
                'id' => (string) $code->id,
            ],
            [[
                'email' => (string) $request->invite_email,
                'locale' => 'fa',
            ]],
            [
                'code' => (string) $code->code,
                'expire_at' => $code->expire_at?->toIso8601String() ?? (string) $code->expire_at,
            ],
            [
                'deduplication_key' => 'member-invitation-code:'.$code->id,
                'priority' => 2,
            ],
        );

        return back()->with('success', 'ایمیل دعوت با موفقیت ارسال شد.');
    }

    private function canonicalBirthDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $context = app(TemporalContextResolver::class)->forUser(Auth::user());

        try {
            if (is_array($value)) {
                if (count($value) < 3 || $value[0] === '' || $value[1] === '' || $value[2] === '') {
                    throw new InvalidArgumentException('Birth date parts are incomplete.');
                }

                return app(TemporalService::class)
                    ->parseDateParts((int) $value[0], (int) $value[1], (int) $value[2], $context)
                    ->toCanonical();
            }

            if (is_string($value)) {
                return app(TemporalService::class)->parseDate(trim($value), $context)->toCanonical();
            }
        } catch (InvalidArgumentException|ValueError) {
            throw ValidationException::withMessages([
                'birth_date' => __('validation.date', ['attribute' => 'birth_date']),
            ]);
        }

        throw ValidationException::withMessages([
            'birth_date' => __('validation.date', ['attribute' => 'birth_date']),
        ]);
    }

    private function bridgeLocalizedBirthDateToLegacyParts(Request $request): void
    {
        $value = $request->input('birth_date');
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $normalized = app(DigitNormalizer::class)->toLatin(trim($value));
        if (preg_match('/^(\\d{4})[\\/-](\\d{2})[\\/-](\\d{2})$/', $normalized, $parts) !== 1) {
            return;
        }

        $request->merge([
            'birth_date' => [(int) $parts[3], (int) $parts[2], (int) $parts[1]],
        ]);
    }
}
