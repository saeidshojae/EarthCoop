<?php

namespace App\Http\Controllers\Auth\Register;

use App\Http\Controllers\Controller;
use App\Rules\JalaliMinimumAge;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Morilog\Jalali\Jalalian;
use App\Models\UserExperience;
use App\Models\User;

class Step1Controller extends Controller
{
    public function show()
    {
        if(auth()->user()->national_id == null){
            return view('auth.register_step1');
        }else{
            $checkUserHave = UserExperience::where('user_id', auth()->user()->id)->first();
            if($checkUserHave == null){
                return redirect('register/step2')->with('success', 'شما نمیتوانید به عقب برگردید اگر نیاز به ویرایش دارید ثبت نام خود را کامل کنید و از درون برنامه ویرایش را انجام دهید');
            }else{
                return redirect('register/step3')->with('success', 'شما نمیتوانید به عقب برگردید اگر نیاز به ویرایش دارید ثبت نام خود را کامل کنید و از درون برنامه ویرایش را انجام دهید');
            }
        }
    }

    protected function convertNumbersToEnglish($string) {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];
    
        $string = str_replace($persian, $english, $string);
        return str_replace($arabic, $english, $string);
    }

    protected function persianToGregorian($persianDate) {
        // فرمت ورودی: 1402-12-28 یا 1402/12/28
        $persianDate = str_replace('/', '-', $persianDate);
        [$jy, $jm, $jd] = explode('-', $persianDate);
        
        $jy = $this->convertNumbersToEnglish($jy);
        $jm = $this->convertNumbersToEnglish($jm);
        $jd = $this->convertNumbersToEnglish($jd);

        $gy = 0; $gm = 0; $gd = 0;
    
        list($gy, $gm, $gd) = $this->j2g($jy, $jm, $jd);
    
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }
    
    protected function j2g($jy, $jm, $jd) {
        $gy = ($jy <= 979) ? 621 : 1600;
        $jy -= ($jy <= 979) ? 0 : 979;
        $days = (365 * $jy) + (int)($jy / 33) * 8 + (int)((($jy % 33) + 3) / 4);
        for ($i = 0; $i < $jm - 1; ++$i)
            $days += [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29][$i];
        $days += $jd - 1;
    
        $gy += 400 * (int)($days / 146097);
        $days %= 146097;
    
        if ($days > 36524) {
            $gy += 100 * --$days / 36524;
            $days %= 36524;
    
            if ($days >= 365)
                $days++;
        }
    
        $gy += 4 * (int)($days / 1461);
        $days %= 1461;
    
        if ($days > 365) {
            $gy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
    
        $gd = $days + 1;
        $sal_a = [0,31,($gy % 4 == 0 && ($gy % 100 != 0 || $gy % 400 == 0)) ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
        for ($gm = 1; $gm <= 12 && $gd > $sal_a[$gm]; $gm++)
            $gd -= $sal_a[$gm];
    
        return [$gy, $gm, $gd];
    }
    
    public function normalizePhoneNumber($number) {
        // حذف تمام فاصله‌ها و کاراکترهای اضافی
        $number = preg_replace('/\s+/', '', $number);
    
        // اگر شماره با 0 شروع شد، حذفش کن
        if (substr($number, 0, 1) === '0') {
            $number = substr($number, 1);
        }
    
        return $number;
    }
    
    public function validateData(Request $request)
    {
        // اعتبارسنجی داده‌ها بدون ذخیره
        $rules = [
            'first_name'   => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'last_name'    => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'birth_date'   => 'required|array|min:3',
            'gender'       => 'required|in:male,female',
            'nationality'  => 'required|string',
            'national_id'  => 'required|string|regex:/^\d{10}$/|unique:users,national_id',
            'country_code' => ['nullable', 'in:+98,+1,+44,+49'],
            'phone'        => 'required|regex:/^(0)?9\d{9}$/',
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
            'phone.regex' => 'شماره تلفن باید دقیقاً ۱۰ رقم باشد و با ۹ شروع شود.',
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
        
        // تبدیل اعداد
        $nationalId = $this->convertNumbersToEnglish($validated['national_id']);
        $phone = $this->normalizePhoneNumber($this->convertNumbersToEnglish($validated['phone']));
        
        // بررسی تکراری بودن شماره تلفن
        $checkPhoneUser = User::where('phone', $phone)->where('id', '!=', auth()->id())->first();
        if($checkPhoneUser != null){
            return response()->json([
                'success' => false,
                'errors' => ['phone' => ['شماره تلفن وارد شده قبلاً در سیستم ثبت شده است.']]
            ], 422);
        }
        
        // اعتبارسنجی کد ملی
        if (!$this->isValidIranianNationalCode($nationalId)) {
            return response()->json([
                'success' => false,
                'errors' => ['national_id' => ['کد ملی وارد شده معتبر نمی‌باشد.']]
            ], 422);
        }
        
        // تبدیل تاریخ تولد
        try {
            [$day, $month, $year] = array_map(
                fn($v) => $this->convertNumbersToEnglish($v),
                $validated['birth_date']
            );
            
            $birthDateMiladi = (new \Morilog\Jalali\Jalalian((int)$year, (int)$month, (int)$day))->toCarbon();
            
            // بررسی سن
            if ($birthDateMiladi->age < 15) {
                return response()->json([
                    'success' => false,
                    'errors' => ['birth_date' => ['سن شما باید حداقل ۱۵ سال باشد.']]
                ], 422);
            }
            
            // فرمت تاریخ برای نمایش
            $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
            $birthDateFormatted = $day . ' ' . $months[(int)$month - 1] . ' ' . $year;
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['birth_date' => ['تاریخ تولد وارد شده معتبر نیست.']]
            ], 422);
        }
        
        // آماده کردن داده‌ها برای نمایش
        $data = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'birth_date' => $birthDateFormatted,
            'gender' => $validated['gender'] == 'male' ? 'مرد' : 'زن',
            'nationality' => $validated['nationality'],
            'national_id' => $nationalId,
            'phone' => $validated['country_code'] . ' ' . $phone,
            'email' => auth()->user()->email,
            'has_password' => !empty($validated['password']),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
    
    public function process(Request $request)
{
    // اعتبارسنجی داده‌ها با پیام‌های خطای واضح
    $rules = [
        'first_name'   => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
        'last_name'    => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
        'birth_date'   => 'required|array|min:3',
        'gender'       => 'required|in:male,female',
        'nationality'  => 'required|string',
        'national_id'  => 'required|string|regex:/^\d{10}$/|unique:users,national_id',
        'country_code' => ['nullable', 'in:+98,+1,+44,+49'],
        'phone'        => 'required|regex:/^(0)?9\d{9}$/',
    ];
    
    // بررسی نیاز به رمز عبور
    $rules['password'] = auth()->user()->password
        ? 'nullable|min:6|confirmed'
        : 'required|min:6|confirmed';

    // پیام‌های خطای سفارشی
    $messages = [
        'first_name.required' => 'وارد کردن نام الزامی است.',
        'first_name.regex' => 'نام باید به زبان فارسی وارد شود. لطفاً از حروف فارسی استفاده کنید و از تایپ لاتین خودداری کنید.',
        'first_name.max' => 'نام نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',
        'last_name.required' => 'وارد کردن نام خانوادگی الزامی است.',
        'last_name.regex' => 'نام خانوادگی باید به زبان فارسی وارد شود. لطفاً از حروف فارسی استفاده کنید و از تایپ لاتین خودداری کنید.',
        'last_name.max' => 'نام خانوادگی نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',
        'phone.required' => 'وارد کردن شماره تلفن الزامی است.',
        'phone.regex' => 'شماره تلفن باید دقیقاً ۱۰ رقم باشد و با ۹ شروع شود. مثال صحیح: 9123456789',
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
    // تبدیل اعداد فارسی به انگلیسی
    $nationalId = $this->convertNumbersToEnglish($validated['national_id']);
    $phone = $this->normalizePhoneNumber($this->convertNumbersToEnglish($validated['phone']));
    
    if($validated['first_name'] != null AND $validated['last_name'] != null AND $validated['gender'] != null AND $validated['national_id'] != null AND $validated['phone'] != null){
       $validated['status'] = 1; 
    }else{
       $validated['status'] = 0; 
    }
    
    // بررسی تکراری بودن شماره تلفن
    if(isset($validated['phone']) AND $validated['phone'] != null){
        $checkPhoneUser = User::where('phone', $phone)->first();
        if($checkPhoneUser != null){
            return back()
                ->withInput()
                ->withErrors(['phone' => 'شماره تلفن وارد شده قبلاً در سیستم ثبت شده است. لطفاً شماره تلفن دیگری وارد کنید.']);
        }
    }

    // اعتبارسنجی کد ملی
    if($validated['national_id'] != null){
        if (!$this->isValidIranianNationalCode($nationalId)) {
            return back()
                ->withInput()
                ->withErrors(['national_id' => 'کد ملی وارد شده معتبر نمی‌باشد. لطفاً کد ملی صحیح خود را وارد کنید.']);
        }   
    }

    // تبدیل تاریخ تولد شمسی به میلادی
    try {
        [$day, $month, $year] = array_map(
            fn($v) => $this->convertNumbersToEnglish($v),
            $validated['birth_date']
        );

        // بررسی صحت تاریخ
        if (!checkdate((int)$month, (int)$day, (int)$year)) {
            // برای تاریخ شمسی، بررسی ساده‌تر
            if ((int)$day < 1 || (int)$day > 31 || (int)$month < 1 || (int)$month > 12) {
                return back()
                    ->with('error', 'تاریخ تولد وارد شده معتبر نیست. لطفاً تاریخ صحیح را وارد کنید.')
                    ->withInput()
                    ->withErrors(['birth_date' => 'تاریخ تولد معتبر نیست.']);
            }
        }

        $birthDateMiladi = (new \Morilog\Jalali\Jalalian((int)$year, (int)$month, (int)$day))->toCarbon();
    } catch (\Exception $e) {
        return back()
            ->withInput()
            ->withErrors(['birth_date' => 'تاریخ تولد وارد شده معتبر نیست. لطفاً تاریخ صحیح را وارد کنید.']);
    }

    // بررسی سن
    if ($birthDateMiladi->age < 15) {
        return back()
            ->withInput()
            ->withErrors(['birth_date' => 'سن شما باید حداقل ۱۵ سال باشد. لطفاً تاریخ تولد صحیح خود را وارد کنید.']);
    }

    // به‌روزرسانی اطلاعات کاربر
    try {
        $user = User::findOrFail(auth()->id());

        $userData = [
            'first_name'   => $validated['first_name'],
            'last_name'    => $validated['last_name'],
            'birth_date'   => $birthDateMiladi->toDateString(),
            'gender'       => $validated['gender'],
            'nationality'  => $validated['nationality'],
            'national_id'  => $nationalId,
            'country_code' => $validated['country_code'],
            'phone'        => $phone,
            'status'        => 1,
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }

        $user->update($userData);

        return redirect()->route('register.step2');
    } catch (\Exception $e) {
        \Log::error('Error updating user in Step1: ' . $e->getMessage());
        return back()
            ->with('error', 'خطایی در ثبت اطلاعات رخ داد. لطفاً دوباره تلاش کنید.')
            ->withInput();
    }
}

protected function isValidIranianNationalCode(string $code): bool
{
    if (!preg_match('/^[0-9]{10}$/', $code)) return false;

    // رد کردن کدهای تکراری مانند 1111111111
    for ($i = 0; $i < 10; $i++) {
        if (preg_match("/^{$i}{10}$/", $code)) return false;
    }

    // الگوریتم بررسی صحت
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += ((10 - $i) * (int)$code[$i]);
    }

    $remainder = $sum % 11;
    $checkDigit = (int)$code[9];

    return ($remainder < 2 && $checkDigit === $remainder) ||
           ($remainder >= 2 && $checkDigit === (11 - $remainder));
}

}
