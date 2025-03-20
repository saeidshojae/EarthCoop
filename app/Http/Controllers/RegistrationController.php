<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OccupationalField;
use App\Models\ExperienceField;
use App\Models\Location;
use App\Models\Group;
use App\Models\InvitationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\GroupService;
use Carbon\Carbon;

class RegistrationController extends Controller
{
    // نمایش صفحه خوش‌آمدگویی (Blade)
    public function showWelcome()
    {
        return view('welcome');
    }
    
    // پردازش موافقت (تیک قوانین) و ثبت اثر انگشت
    public function processAgreement(Request $request)
    {
        $request->validate([
            'fingerprint_id' => 'nullable|string'
        ]);
        session(['fingerprint_id' => $request->fingerprint_id]);
        return redirect()->route('register.form');
    }

    // نمایش فرم ثبت‌نام اولیه
    public function showRegisterForm()
    {
        return view('auth.register');
    }
    
    // پردازش ثبت‌نام اولیه
    public function processRegister(Request $request)
    {
        $request->validate([
            'email'    => 'nullable|email|unique:users,email',
            'phone'    => 'nullable|unique:users,phone',
            'password' => 'required|min:6|confirmed',
            'invitation_code' => 'required|exists:invitation_codes,code'
        ]);
        
        // بررسی اعتبار کد دعوت
        $invitationCode = InvitationCode::where('code', $request->invitation_code)
            ->where('used', false)
            ->first();
            
        if (!$invitationCode) {
            return back()->withErrors(['invitation_code' => 'کد دعوت نامعتبر است یا قبلاً استفاده شده است.']);
        }
        
        $user = User::create([
            'email'          => $request->email,
            'phone'          => $request->phone,
            'password'       => Hash::make($request->password),
            'fingerprint_id' => session('fingerprint_id'),
            'invitation_code' => $request->invitation_code
        ]);
        
        // علامت‌گذاری کد دعوت به عنوان استفاده شده
        $invitationCode->update(['used' => true]);
        
        auth()->login($user);
        return redirect()->route('register.step1');
    }
    
    // استپ 1: اطلاعات هویتی
    public function showStep1()
    {
        return view('auth.register_step1');
    }
    
    public function processStep1(Request $request)
    {
        $request->validate([
            'first_name'   => 'required|string|max:50',
            'last_name'    => 'required|string|max:50',
            'birth_date'   => 'required|date',
            'gender'       => 'required|in:male,female',
            'nationality'  => 'required|string',
            'national_id'  => 'required|string',
            'phone'        => 'required',
            'email'        => 'required|email',
        ]);
        
        $user = auth()->user();
        
        $user->update([
            'first_name'  => $request->first_name,
            'last_name'   => $request->last_name,
            'birth_date'  => $request->birth_date,
            'gender'      => $request->gender,
            'nationality' => $request->nationality,
            'national_id' => $request->national_id,
            'phone'       => $request->phone,
            'email'       => $request->email,
        ]);
        
        return redirect()->route('register.step2');
    }
    
    // استپ 2: انتخاب زمینه‌های صنفی و تخصص
    public function showStep2()
    {
        $occupationalFields = OccupationalField::whereNull('parent_id')->get();
        $experienceFields   = ExperienceField::whereNull('parent_id')->get();
        return view('auth.register_step2', compact('occupationalFields','experienceFields'));
    }
    
    public function processStep2(Request $request)
    {
        $request->validate([
            'occupational_fields' => 'required|array',
            'experience_fields'   => 'required|array',
        ]);
        
        $user = auth()->user();
        $user->occupationalFields()->sync($request->occupational_fields);
        $user->experienceFields()->sync($request->experience_fields);
        
        return redirect()->route('register.step3');
    }
    
    // استپ 3: انتخاب مکان (منوهای وابسته)
    public function showStep3()
    {
        // ارسال اطلاعات اولیه سطح اول (مثلاً قاره)
        $continents = Location::where('level', 'continent')->get();
        return view('auth.register_step3', compact('continents'));
    }
    
    public function processStep3(Request $request)
    {
        $request->validate([
            'continent_id'     => 'required|exists:locations,id',
            'country_id'       => 'required|exists:locations,id',
            'province_id'      => 'required|exists:locations,id',
            'county_id'        => 'required|exists:locations,id',
            'section_id'       => 'required|exists:locations,id',
            'city_id'          => 'required|exists:locations,id',
            'region_id'        => 'required|exists:locations,id',
            'neighborhood_id'  => 'required|exists:locations,id',
            'street_id'        => 'nullable|exists:locations,id',
            'alley_id'         => 'nullable|exists:locations,id',
        ]);
        
        $user = auth()->user();
        $locationIds = [
            $request->continent_id,
            $request->country_id,
            $request->province_id,
            $request->county_id,
            $request->section_id,
            $request->city_id,
            $request->region_id,
            $request->neighborhood_id,
        ];
        if($request->street_id){
            $locationIds[] = $request->street_id;
        }
        if($request->alley_id){
            $locationIds[] = $request->alley_id;
        }
        $user->locations()->sync($locationIds);
        
        // اختصاص گروه‌ها به کاربر
        GroupService::assignGroups($user);
        
        return redirect()->route('home');
    }
}