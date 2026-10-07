<?php

namespace App\Http\Controllers\Auth\Register;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserExperience;
use App\Temporal\Context\TemporalContext;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Policies\AgePolicy;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Step1Controller extends Controller
{
    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
        private readonly AgePolicy $agePolicy,
    ) {
    }

    public function show()
    {
        if (auth()->user()->national_id == null) {
            $context = $this->temporalContexts->defaultContext();
            $birthYearMax = $this->temporal->year(
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
                $context,
            ) - 15;
            $birthYearMin = $birthYearMax - 135;

            return view('auth.register_step1', compact('birthYearMax', 'birthYearMin'));
        }

        $checkUserHave = UserExperience::where('user_id', auth()->user()->id)->first();
        if ($checkUserHave == null) {
            return redirect('register/step2')->with('success', 'شما نمیتوانید به عقب برگردید اگر نیاز به ویرایش دارید ثبت نام خود را کامل کنید و از درون برنامه ویرایش را انجام دهید');
        }

        return redirect('register/step3')->with('success', 'شما نمیتوانید به عقب برگردید اگر نیاز به ویرایش دارید ثبت نام خود را کامل کنید و از درون برنامه ویرایش را انجام دهید');
    }

    protected function convertNumbersToEnglish($string)
    {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $arabic = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];

        $string = str_replace($persian, $english, $string);

        return str_replace($arabic, $english, $string);
    }

    public function normalizePhoneNumber($number)
    {
        $number = preg_replace('/\s+/', '', $number);

        if (substr($number, 0, 1) === '0') {
            $number = substr($number, 1);
        }

        return $number;
    }

    public function validateData(Request $request)
    {
        $rules = [
            'first_name' => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'last_name' => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'birth_date' => 'required|array|min:3',
            'gender' => 'required|in:male,female',
            'nationality' => 'required|string',
            'national_id' => 'required|string|regex:/^\d{10}$/|unique:users,national_id',
            'country_code' => ['required', Rule::in(array_column(config('phone-countries', []), 'code'))],
            'phone' => [
                'required',
                'regex:/^\d{6,15}$/',
                Rule::unique('users', 'phone')->where(
                    fn ($query) => $query->where('phone_country_code', $request->input('country_code'))
                ),
            ],
        ];

        $rules['password'] = auth()->user()->password
            ? 'nullable|min:6|confirmed'
            : 'required|min:6|confirmed';

        $messages = [
            'first_name.required' => 'وارد کردن نام الزامی است.',
            'first_name.regex' => 'نام باید به زبان فارسی وارد شود.',
            'last_name.required' => 'وارد کردن نام خانوادگی الزامی است.',
            'last_name.regex' => 'نام خانوادگی باید به زبان فارسی وارد شود.',
            'phone.required' => 'وارد کردن شماره تلفن الزامی است.',
            'phone.regex' => 'شماره تلفن را بدون کد کشور و فقط با رقم وارد کنید.',
            'national_id.required' => 'وارد کردن کد ملی الزامی است.',
            'national_id.regex' => 'کد ملی باید دقیقاً ۱۰ رقم باشد.',
            'national_id.unique' => 'این کد ملی قبلاً در سیستم ثبت شده است.',
            'birth_date.required' => 'وارد کردن تاریخ تولد الزامی است.',
            'gender.required' => 'انتخاب جنسیت الزامی است.',
            'nationality.required' => 'انتخاب ملیت الزامی است.',
            'password.required' => 'وارد کردن رمز عبور الزامی است.',
            'password.min' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
            'password.confirmed' => 'رمز عبور و تأیید رمز عبور مطابقت ندارند.',
        ];

        $validated = $request->validate($rules, $messages);
        $nationalId = $this->convertNumbersToEnglish($validated['national_id']);
        $phone = $this->normalizePhoneNumber($this->convertNumbersToEnglish($validated['phone']));

        $checkPhoneUser = User::where('phone_country_code', $validated['country_code'])
            ->where('phone', $phone)
            ->where('id', '!=', auth()->id())
            ->first();
        if ($checkPhoneUser != null) {
            return response()->json([
                'success' => false,
                'errors' => ['phone' => ['شماره تلفن وارد شده قبلاً در سیستم ثبت شده است.']],
            ], 422);
        }

        if (! $this->isValidIranianNationalCode($nationalId)) {
            return response()->json([
                'success' => false,
                'errors' => ['national_id' => ['کد ملی وارد شده معتبر نمی‌باشد.']],
            ], 422);
        }

        $context = $this->temporalContexts->defaultContext();

        try {
            $birthDate = $this->parseBirthDate($validated['birth_date'], $context);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'errors' => ['birth_date' => ['تاریخ تولد وارد شده معتبر نیست.']],
            ], 422);
        }

        if (! $this->meetsMinimumRegistrationAge($birthDate, $context)) {
            return response()->json([
                'success' => false,
                'errors' => ['birth_date' => ['سن شما باید حداقل ۱۵ سال باشد.']],
            ], 422);
        }

        $data = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'birth_date' => $this->temporal->date($birthDate, $context, 'medium'),
            'gender' => $validated['gender'] == 'male' ? 'مرد' : 'زن',
            'nationality' => $validated['nationality'],
            'national_id' => $nationalId,
            'phone' => $validated['country_code'] . ' ' . $phone,
            'email' => auth()->user()->email,
            'has_password' => ! empty($validated['password']),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function process(Request $request)
    {
        $rules = [
            'first_name' => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'last_name' => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'birth_date' => 'required|array|min:3',
            'gender' => 'required|in:male,female',
            'nationality' => 'required|string',
            'national_id' => 'required|string|regex:/^\d{10}$/|unique:users,national_id',
            'country_code' => ['required', Rule::in(array_column(config('phone-countries', []), 'code'))],
            'phone' => [
                'required',
                'regex:/^\d{6,15}$/',
                Rule::unique('users', 'phone')->where(
                    fn ($query) => $query->where('phone_country_code', $request->input('country_code'))
                ),
            ],
        ];

        $rules['password'] = auth()->user()->password
            ? 'nullable|min:6|confirmed'
            : 'required|min:6|confirmed';

        $messages = [
            'first_name.required' => 'وارد کردن نام الزامی است.',
            'first_name.regex' => 'نام باید به زبان فارسی وارد شود. لطفاً از حروف فارسی استفاده کنید و از تایپ لاتین خودداری کنید.',
            'first_name.max' => 'نام نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',
            'last_name.required' => 'وارد کردن نام خانوادگی الزامی است.',
            'last_name.regex' => 'نام خانوادگی باید به زبان فارسی وارد شود. لطفاً از حروف فارسی استفاده کنید و از تایپ لاتین خودداری کنید.',
            'last_name.max' => 'نام خانوادگی نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',
            'phone.required' => 'وارد کردن شماره تلفن الزامی است.',
            'phone.regex' => 'شماره تلفن را بدون کد کشور و فقط با رقم وارد کنید.',
            'national_id.required' => 'وارد کردن کد ملی الزامی است.',
            'national_id.regex' => 'کد ملی باید دقیقاً ۱۰ رقم باشد. لطفاً کد ملی ۱۰ رقمی خود را وارد کنید.',
            'national_id.unique' => 'این کد ملی قبلاً در سیستم ثبت شده است. لطفاً کد ملی صحیح خود را وارد کنید.',
            'birth_date.required' => 'وارد کردن تاریخ تولد الزامی است.',
            'birth_date.min' => 'لطفاً تاریخ تولد را کامل وارد کنید (روز، ماه و سال).',
            'gender.required' => 'انتخاب جنسیت الزامی است.',
            'gender.in' => 'لطفاً جنسیت را انتخاب کنید (مرد یا زن).',
            'nationality.required' => 'انتخاب ملیت الزامی است.',
            'password.required' => 'وارد کردن رمز عبور الزامی است.',
            'password.min' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
            'password.confirmed' => 'رمز عبور و تأیید رمز عبور مطابقت ندارند. لطفاً دوباره وارد کنید.',
        ];

        $validated = $request->validate($rules, $messages);
        $nationalId = $this->convertNumbersToEnglish($validated['national_id']);
        $phone = $this->normalizePhoneNumber($this->convertNumbersToEnglish($validated['phone']));

        if ($validated['first_name'] != null && $validated['last_name'] != null && $validated['gender'] != null && $validated['national_id'] != null && $validated['phone'] != null) {
            $validated['status'] = 1;
        } else {
            $validated['status'] = 0;
        }

        if (isset($validated['phone']) && $validated['phone'] != null) {
            $checkPhoneUser = User::where('phone_country_code', $validated['country_code'])
                ->where('phone', $phone)
                ->first();
            if ($checkPhoneUser != null) {
                return back()
                    ->withInput()
                    ->withErrors(['phone' => 'شماره تلفن وارد شده قبلاً در سیستم ثبت شده است. لطفاً شماره تلفن دیگری وارد کنید.']);
            }
        }

        if ($validated['national_id'] != null && ! $this->isValidIranianNationalCode($nationalId)) {
            return back()
                ->withInput()
                ->withErrors(['national_id' => 'کد ملی وارد شده معتبر نمی‌باشد. لطفاً کد ملی صحیح خود را وارد کنید.']);
        }

        $context = $this->temporalContexts->defaultContext();

        try {
            $birthDate = $this->parseBirthDate($validated['birth_date'], $context);
        } catch (\Throwable) {
            return back()
                ->withInput()
                ->withErrors(['birth_date' => 'تاریخ تولد وارد شده معتبر نیست. لطفاً تاریخ صحیح را وارد کنید.']);
        }

        if (! $this->meetsMinimumRegistrationAge($birthDate, $context)) {
            return back()
                ->withInput()
                ->withErrors(['birth_date' => 'سن شما باید حداقل ۱۵ سال باشد. لطفاً تاریخ تولد صحیح خود را وارد کنید.']);
        }

        try {
            $user = User::findOrFail(auth()->id());

            $userData = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'birth_date' => $birthDate->toCanonical(),
                'gender' => $validated['gender'],
                'nationality' => $validated['nationality'],
                'national_id' => $nationalId,
                'phone_country_code' => $validated['country_code'],
                'phone' => $phone,
                'status' => 1,
            ];

            if (! empty($validated['password'])) {
                $userData['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
            }

            $user->update($userData);

            return redirect()->route('register.step2');
        } catch (\Throwable $e) {
            \Log::error('Error updating user in Step1: ' . $e->getMessage());

            return back()
                ->with('error', 'خطایی در ثبت اطلاعات رخ داد. لطفاً دوباره تلاش کنید.')
                ->withInput();
        }
    }

    private function parseBirthDate(array $parts, TemporalContext $context): LocalDate
    {
        [$day, $month, $year] = array_map(
            fn ($value): int => (int) $this->convertNumbersToEnglish($value),
            array_values($parts),
        );

        return $this->temporal->parseDateParts($day, $month, $year, $context);
    }

    private function meetsMinimumRegistrationAge(LocalDate $birthDate, TemporalContext $context): bool
    {
        $today = new DateTimeImmutable('today', new DateTimeZone($context->timezone()));

        return $this->agePolicy->meetsMinimumAge($birthDate, 15, $today);
    }

    protected function isValidIranianNationalCode(string $code): bool
    {
        if (! preg_match('/^[0-9]{10}$/', $code)) {
            return false;
        }

        for ($i = 0; $i < 10; $i++) {
            if (preg_match("/^{$i}{10}$/", $code)) {
                return false;
            }
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((10 - $i) * (int) $code[$i]);
        }

        $remainder = $sum % 11;
        $checkDigit = (int) $code[9];

        return ($remainder < 2 && $checkDigit === $remainder)
            || ($remainder >= 2 && $checkDigit === (11 - $remainder));
    }
}
