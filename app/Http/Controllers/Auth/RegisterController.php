<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Province;
use App\Models\County;
use App\Models\District;
use App\Models\Settlement;
use App\Models\Locality;
use App\Models\Neighborhood;
use App\Models\Street;
use App\Models\Alley;
use App\Models\JobField;
use App\Models\Specialization;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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
        ]);

        // ذخیره داده‌های مرحله ۱ در سشن
        $request->session()->put('register_step1', $request->all());

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
        $locations = [
            'continents' => Continent::all(),
            'countries' => Country::all(),
            'provinces' => Province::all(),
            'counties' => County::all(),
            'districts' => District::all(),
            'settlements' => Settlement::all(),
            'localities' => Locality::all(),
            'neighborhoods' => Neighborhood::all(),
            'streets' => Street::all(),
            'alleys' => Alley::all(),
        ];

        return view('auth.register_step3', $locations);
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
            'password' => Hash::make($data['password']),
            'location' => $data['location'],
        ]);

        // اتصال رسته‌های صنفی و تخصص‌ها به کاربر
        $user->jobFields()->sync($data['job_fields']);
        $user->specializations()->sync($data['specializations']);

        // ورود خودکار کاربر پس از ثبت‌نام
        auth()->login($user);

        return redirect($this->redirectPath());
    }
}
