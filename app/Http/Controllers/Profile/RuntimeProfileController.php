<?php

namespace App\Http\Controllers\Profile;

use App\Models\Candidate;
use App\Models\ChatRequest;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\UserExperience;
use App\Services\ProfileCompletionService;
use App\Services\Groups\CanonicalGroupMembershipReconciler;

/**
 * Transitional canonical read surface for the member profile.
 *
 * The legacy ProfileController remains the rollback implementation while
 * canonical runtime is disabled. Keeping this adapter small avoids rewriting
 * the large legacy controller merely to remove its Address entrance gate.
 */
class RuntimeProfileController
{
    public function showProfile()
    {
        if (! (bool) config('location-governance.runtime_enabled')) {
            return app(ProfileController::class)->showProfile();
        }

        $user = auth()->user();
        $checkUserHave = UserExperience::where('user_id', $user->id)->first();

        if ($user->national_id == null) {
            return redirect('profile/edit')->with('success', 'شما هنوز اطلاعات هویتی خود را تکمیل نکرده اید، ابتدا با وارد کردن اطلاعات هویتی حساب کاربری خود را فعال و سپس وارد پروفایل خود شوید');
        }

        if ($checkUserHave == null) {
            return redirect('register/step2')->with('success', 'شما نمیتوانید وارد برنامه شوید، لطفا مراحل ثبت نام را کامل کنید و اگر نیاز به ویرایش دارید پس از ثبت نام از درون برنامه اقدام کنید');
        }

        if (! app(ProfileCompletionService::class)->hasRequiredResidence($user)) {
            return redirect('register/step3')->with('success', 'شما نمیتوانید وارد برنامه شوید، لطفا مراحل ثبت نام را کامل کنید و اگر نیاز به ویرایش دارید پس از ثبت نام از درون برنامه اقدام کنید');
        }

        $candidates = Candidate::where('user_id', $user->id)->where('accept_status', 1)->get();

        if ((bool) config('location-governance.groups_enabled', false)) {
            $materializedIds = collect(app(CanonicalGroupMembershipReconciler::class)->reconcile($user))
                ->pluck('id')
                ->filter()
                ->values();

            $groups = $materializedIds->isEmpty()
                ? collect()
                : $user->groups()
                    ->with('governanceArea')
                    ->whereIn('groups.id', $materializedIds->all())
                    ->wherePivot('status', 1)
                    ->get();

            $groups->each(function (Group $group): void {
                if ($group->governanceArea !== null) {
                    $group->setAttribute(
                        'location_level',
                        $this->presentationLevelFor((string) $group->governanceArea->governance_type),
                    );
                }
            });

            $generalGroups = $groups->where('dimension_key', 'public')->values();
            $specialityGroups = $groups->where('dimension_key', 'profession')->values();
            $experienceGroups = $groups->where('dimension_key', 'specialty')->values();
            $ageGroups = $groups->where('dimension_key', 'age')->values();
            $genderGroups = $groups->where('dimension_key', 'gender')->values();
        } else {
            $generalGroups = $user->groups()->where('group_type', 0)->get();
            $specialityGroups = $user->groups()->whereNotNull('specialty_id')->whereNull('experience_id')->get();
            $experienceGroups = $user->groups()->whereNull('specialty_id')->whereNotNull('experience_id')->get();
            $ageGroups = $user->groups()->where('group_type', 3)->get();
            $genderGroups = $user->groups()->where('group_type', 4)->get();
        }

        GroupUser::where('status', 1)
            ->where('expired', '<', now())
            ->get()
            ->each(function ($groupUser): void {
                $groupUser->delete();
            });

        $joinGroupRequests = GroupUser::where('user_id', $user->id)
            ->where('status', 0)
            ->where('role', 4)
            ->get();

        $chatRequests = ChatRequest::where('receiver_id', $user->id)
            ->where('status', 'pending')
            ->with('sender')
            ->latest()
            ->get();

        return view('profile.profile', compact(
            'user',
            'candidates',
            'generalGroups',
            'specialityGroups',
            'experienceGroups',
            'ageGroups',
            'genderGroups',
            'chatRequests',
            'joinGroupRequests'
        ));
    }
    private function presentationLevelFor(string $governanceType): ?string
    {
        return match ($governanceType) {
            'global' => 'global',
            'continent' => 'continent',
            'country' => 'country',
            'province' => 'province',
            'county' => 'county',
            'section' => 'section',
            'city', 'rural_district' => 'city',
            'urban_region', 'village' => 'region',
            'local', 'neighborhood' => 'neighborhood',
            default => null,
        };
    }


}
