<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Location; // استفاده از مدل Location به جای مدل‌های قدیمی
use App\Models\JobField;
use App\Models\Specialization;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    use RegistersUsers;

    // مسیر هدایت پس از ثبت‌نام موفقیت‌آمیز
    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * نمایش فرم مرحله ۱
     */
    public function showStep1Form()
    {
        return view('auth.register_step1');
    }

    /**
     * مدیریت ارسال داده‌های فرم مرحله ۱
     */
    public function postStep1Form(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
            'national_id' => 'required|string|max:255|unique:users',
            'phone' => 'required|string|max:255|unique:users',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed', // اعتبارسنجی فیلد گذرواژه
        ]);

        // ذخیره داده‌های مرحله ۱ در سشن
        $request->session()->put('register_step1', $request->except('password')); // فقط داده‌های غیر از گذرواژه را ذخیره کنید

        return redirect()->route('register.step2');
    }

    /**
     * نمایش فرم مرحله ۲
     */
    public function showStep2Form()
    {
        $jobFields = JobField::with('children.children')->where('parent_id', null)->get();
        $specializations = Specialization::with('children.children')->where('parent_id', null)->get();

        return view('auth.register_step2', compact('jobFields', 'specializations'));
    }

    /**
     * مدیریت ارسال داده‌های فرم مرحله ۲
     */
    public function postStep2Form(Request $request)
    {
        $request->validate([
            'job_fields' => 'required|array|min:1',
            'specializations' => 'required|array|min:1',
        ]);

        // ذخیره داده‌های مرحله ۲ در سشن
        $request->session()->put('register_step2', $request->only('job_fields', 'specializations'));

        return redirect()->route('register.step3');
    }

    /**
     * نمایش فرم مرحله ۳
     */
    public function showStep3Form()
    {
        // واکشی قاره‌ها از جدول locations
        $continents = Location::whereNull('parent_id')->get();

        return view('auth.register_step3', compact('continents'));
    }

    /**
     * مدیریت ارسال داده‌های فرم مرحله ۳
     */
    public function postStep3Form(Request $request)
    {
        $request->validate([
            'location' => 'required|string',
        ]);

        $step1Data = $request->session()->get('register_step1', []);
        $step2Data = $request->session()->get('register_step2', []);

        // بررسی کامل بودن داده‌های سشن
        if (empty($step1Data) || empty($step2Data)) {
            return redirect()->route('register.step1')->withErrors(['error' => 'اطلاعات مورد نیاز برای ثبت‌نام کامل نیست. لطفاً مراحل ثبت‌نام را از ابتدا شروع کنید.']);
        }

        // ادغام تمامی داده‌ها
        $data = array_merge($step1Data, $step2Data, $request->all());

        // ایجاد کاربر جدید
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'nationality' => $data['nationality'],
            'national_id' => $data['national_id'],
            'phone' => $data['phone'],
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'email' => $data['email'],
            'password' => Hash::make($request->session()->get('register_step1.password')), // هش کردن گذرواژه
        ]);

        // اتصال رسته‌های صنفی و تخصص‌ها به کاربر
        $user->jobFields()->sync($data['job_fields']);
        $user->specializations()->sync($data['specializations']);

        // ورود خودکار کاربر پس از ثبت‌نام
        auth()->login($user);

        return redirect($this->redirectPath());
    }
}
