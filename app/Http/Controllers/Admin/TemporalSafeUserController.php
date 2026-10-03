<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\NajmBahar\Services\MembershipRemovalService;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\LocationGovernance\UserResidenceReadModel;
use App\Services\Users\UserManagementService;
use App\Temporal\Context\TemporalContext;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Policies\AgePolicy;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
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

    public function index(Request $request)
    {
        $query = User::members()->with([
            'address.country', 'address.province', 'address.county', 'address.section',
            'address.city', 'address.rural', 'address.region', 'address.village',
            'address.neighborhood', 'address.street', 'address.alley',
            'occupationalFields', 'experienceFields', 'groups',
        ]);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('gender')) {
            $query->where('gender', $request->input('gender'));
        }
        if ($request->filled('email_verified')) {
            $request->input('email_verified') === '1'
                ? $query->whereNotNull('email_verified_at')
                : $query->whereNull('email_verified_at');
        }
        if ($request->filled('province_id')) {
            $provinceId = $request->input('province_id');
            $query->whereHas('address', fn ($q) => $q->where('province_id', $provinceId));
        }

        $context = $this->temporalContexts->defaultContext();
        $this->applyLocalizedDateRange($query, $request, 'created_at', 'created_from', 'created_to', $context);

        $users = $query->orderBy('created_at', 'desc')->get();
        $today = $this->localToday($context);

        $stats = [
            'total' => User::members()->count(),
            'active' => User::members()->where('status', 'active')->count(),
            'inactive' => User::members()->where('status', 'inactive')->count(),
            'suspended' => User::members()->where('status', 'suspended')->count(),
            'verified' => User::members()->whereNotNull('email_verified_at')->count(),
            'unverified' => User::members()->whereNull('email_verified_at')->count(),
            'today' => User::members()->whereBetween('created_at', [
                $this->temporal->startOfDay($today, $context),
                $this->temporal->endOfDay($today, $context),
            ])->count(),
            'this_week' => User::members()->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => User::members()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        $provinces = \App\Models\Province::orderBy('name')->get();
        $registrationChartData = [];
        $canonicalToday = new DateTimeImmutable($today->toCanonical(), new DateTimeZone('UTC'));
        for ($i = 29; $i >= 0; $i--) {
            $date = LocalDate::fromCanonical($canonicalToday->modify("-{$i} days")->format('Y-m-d'));
            $count = User::members()->whereBetween('created_at', [
                $this->temporal->startOfDay($date, $context),
                $this->temporal->endOfDay($date, $context),
            ])->count();
            $registrationChartData[] = [
                'date' => $this->temporal->date($date, $context, 'short'),
                'count' => $count,
            ];
        }

        $geographicDistribution = User::members()
            ->whereHas('address', fn ($q) => $q->whereNotNull('province_id'))
            ->with('address.province')
            ->get()
            ->groupBy(fn ($user) => $user->address && $user->address->province ? $user->address->province->name : 'نامشخص')
            ->map(fn ($users) => $users->count())
            ->sortDesc()
            ->take(10);

        return view('admin.user.index', compact(
            'users', 'stats', 'provinces', 'registrationChartData', 'geographicDistribution',
        ));
    }

    public function store(Request $request)
    {
        $inputs = $request->validate([
            'email' => 'required|email|unique:users,email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'first_name' => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'last_name' => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'birth_date' => 'required',
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

        $birthDate = $this->parseBirthDateInput($inputs['birth_date']);
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
        $birthDate = $this->parseBirthDateInput($request->input('birth_date'));
        if ($birthDate === null) {
            return back()->with('error', 'تاریخ تولد وارد شده معتبر نیست')->withInput();
        }
        if (! $this->agePolicy->meetsMinimumAge($birthDate, 15)) {
            return back()->with('error', 'سن شما باید حداقل ۱۵ سال باشد')->withInput();
        }

        // SafeUserController still delegates non-lifecycle fields to the legacy updater.
        // Bridge only at that internal boundary; external admin input remains locale-aware.
        $jalali = $this->temporal->date(
            $birthDate,
            $this->temporalContexts->forLocale('fa', 'UTC'),
            'short',
        );
        [$year, $month, $day] = explode('/', $jalali);
        $request->merge(['birth_date' => [$day, $month, $year]]);

        return parent::update($request, $user);
    }

    public function transactions(Request $request, User $user)
    {
        $query = \App\Models\UserPointTransaction::where('user_id', $user->id);
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        $this->applyLocalizedDateRange(
            $query,
            $request,
            'created_at',
            'date_from',
            'date_to',
            $this->temporalContexts->defaultContext(),
        );

        $transactions = $query->orderByDesc('created_at')->paginate(25)->appends($request->except('page'));
        $currentPoints = optional(\App\Models\UserPoint::where('user_id', $user->id)->first())->points ?? 0;

        return view('admin.user.transactions', compact('user', 'transactions', 'currentPoints'));
    }

    private function applyLocalizedDateRange(
        $query,
        Request $request,
        string $column,
        string $fromField,
        string $toField,
        TemporalContext $context,
    ): void {
        if ($request->filled($fromField)) {
            try {
                $from = $this->temporal->parseDate((string) $request->input($fromField), $context);
                $query->where($column, '>=', $this->temporal->startOfDay($from, $context));
            } catch (\Throwable) {
            }
        }
        if ($request->filled($toField)) {
            try {
                $to = $this->temporal->parseDate((string) $request->input($toField), $context);
                $query->where($column, '<=', $this->temporal->endOfDay($to, $context));
            } catch (\Throwable) {
            }
        }
    }

    private function localToday(TemporalContext $context): LocalDate
    {
        $localNow = new DateTimeImmutable('now', new DateTimeZone($context->timezone()));
        return LocalDate::fromCanonical($localNow->format('Y-m-d'));
    }

    private function parseBirthDateInput(mixed $value): ?LocalDate
    {
        try {
            if (is_string($value) && trim($value) !== '') {
                return $this->temporal->parseDate(
                    trim($value),
                    $this->temporalContexts->defaultContext(),
                );
            }

            if (is_array($value) && count($value) >= 3) {
                [$day, $month, $year] = array_map(
                    fn ($part): int => (int) $this->convertNumbersToEnglish((string) $part),
                    array_slice($value, 0, 3),
                );

                return $this->temporal->parseDateParts(
                    $day,
                    $month,
                    $year,
                    $this->temporalContexts->defaultContext(),
                );
            }
        } catch (InvalidArgumentException|\ValueError) {
            return null;
        }

        return null;
    }
}
