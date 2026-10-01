<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\NajmBahar\Services\MembershipRemovalService;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\LocationGovernance\UserResidenceReadModel;
use App\Services\Users\UserManagementService;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Policies\AgePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Temporal boundary for the active admin-user controller binding.
 *
 * It deliberately extends the estate/location-safe controller so membership,
 * lifecycle and canonical-residence protections remain authoritative while
 * localized date input is moved behind the Temporal subsystem.
 */
final class TemporalSafeUserController extends SafeUserController
{
    public function __construct(
        MembershipRemovalService $membershipRemoval,
        UserManagementService $userManagement,
        CanonicalGroupMembershipReconciler $canonicalGroupMembershipReconciler,
        UserResidenceReadModel $residenceReadModel,
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
        private readonly AgePolicy $agePolicy,
    ) {
        parent::__construct(
            $membershipRemoval,
            $userManagement,
            $canonicalGroupMembershipReconciler,
            $residenceReadModel,
        );
    }

    public function store(Request $request)
    {
        $inputs = $request->validate([
            'email' => 'required|email|unique:users,email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'first_name' => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'last_name' => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'birth_date' => 'required|array|min:3',
            'gender' => 'required|in:male,female',
            'national_id' => 'required|string|regex:/^\d{10}$/|unique:users,national_id',
            'phone' => 'required|regex:/^(0)?9\d{9}$/|unique:users,phone',
            'password' => 'required|min:6|confirmed',
        ]);

        $nationalId = $this->convertNumbersToEnglish($inputs['national_id']);
        $this->normalizePhoneNumber($this->convertNumbersToEnglish($inputs['phone']));

        if (! $this->isValidIranianNationalCode($nationalId)) {
            return back()->with('error', 'کد ملی وارد شده معتبر نیست')->withInput();
        }

        $birthDate = $this->parseBirthDateParts((array) $inputs['birth_date']);
        if ($birthDate === null) {
            return back()->with('error', 'تاریخ تولد وارد شده معتبر نیست')->withInput();
        }

        if (! $this->agePolicy->meetsMinimumAge($birthDate, 15)) {
            return back()->with('error', 'سن شما باید حداقل ۱۵ سال باشد')->withInput();
        }

        $inputs['birth_date'] = $birthDate->toCanonical();
        $inputs['password'] = Hash::make($inputs['password']);

        User::create($inputs);

        return redirect()->route('admin.users.index')->with('success', 'کاربر با موفقیت ایجاد شد');
    }

    public function update(Request $request, User $user)
    {
        $birthDate = $this->parseBirthDateParts((array) $request->input('birth_date', []));
        if ($birthDate === null) {
            return back()->with('error', 'تاریخ تولد وارد شده معتبر نیست')->withInput();
        }

        if (! $this->agePolicy->meetsMinimumAge($birthDate, 15)) {
            return back()->with('error', 'سن شما باید حداقل ۱۵ سال باشد')->withInput();
        }

        // SafeUserController currently delegates non-lifecycle profile fields to
        // the legacy parent updater. Feed that boundary a Jalali triplet derived
        // from the canonical LocalDate so the active path is locale-neutral
        // without duplicating the protected lifecycle/reconciliation logic here.
        $jalali = $this->temporal->date(
            $birthDate,
            $this->temporalContexts->forLocale('fa', 'UTC'),
            'short',
        );
        [$year, $month, $day] = explode('/', $jalali);
        $request->merge(['birth_date' => [$day, $month, $year]]);

        return parent::update($request, $user);
    }

    private function parseBirthDateParts(array $parts): ?\App\Temporal\ValueObjects\LocalDate
    {
        if (count($parts) < 3) {
            return null;
        }

        try {
            [$day, $month, $year] = array_map(
                fn ($value): int => (int) $this->convertNumbersToEnglish((string) $value),
                array_slice($parts, 0, 3),
            );

            return $this->temporal->parseDateParts(
                $day,
                $month,
                $year,
                $this->temporalContexts->defaultContext(),
            );
        } catch (InvalidArgumentException|\ValueError) {
            return null;
        }
    }
}
