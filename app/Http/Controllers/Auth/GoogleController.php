<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Support\Facades\Auth;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        $login = request()->has('login') && request()->get('login') == '1';
        $state = $login ? 'login' : 'register';
        return Socialite::driver('google')
            ->with(['state' => $state])
            ->redirect();
    }
    
    private function getIncompleteStep(User $user): ?string
{
    if ($user->password == null || $user->national_id == null) {
        if(Address::where('user_id', $user->id)->exists()){
            return 'home';
        }else{
            return 'register.step1';
        }
    }

    if (!UserExperience::where('user_id', $user->id)->exists()) {
        return 'register.step2';
    }

    if (!Address::where('user_id', $user->id)->exists()) {
        return 'register.step3';
    }

    return null;
}

    
    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $state = request()->get('state'); // 'login' or 'register'
        $user = User::whereEmail($googleUser->getEmail())->first();
    
        if (!$user) {
            if ($state === 'login') {
                return redirect()->route('welcome')->with('success', 'حسابی با این ایمیل وجود ندارد. لطفا ابتدا ثبت‌نام کنید.');
            }
    
            // ساخت کاربر جدید در حالت register
            $user = User::create([
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'email_verified_at' => now(),
            ]);
            Auth::login($user);
            return redirect()->route('register.step1')->with('success', 'سهامدار عزیز، تبریک! شما با موفقیت به EarthCoop پیوستید. دنیای همکاری های بزرگ در انتظار شماست. لطفا دقایقی وقت بگذارید و در سه مرحله اطلاعات هویتی، اطلاعات صنفی و تخصصی، و اطلاعات مکانی خود را وارد و عضویت خود را فعال نمای');
        }
    
        // لاگین موفق
        Auth::login($user);
    
        // بررسی مراحل ناقص
        if ($step = $this->getIncompleteStep($user)) {
            if($step == 'home'){
                $msg = 'مراحل ثبت‌نام شما هنوز کامل نشده جهت استفاده از تمام امکانات برنامه در اولین فرصت اطلاعات هویتی خود را کامل نمایید';
            }else{
                $msg = 'مراحل ثبت‌نام شما هنوز کامل نشده، لطفاً ادامه دهید.';
            }
            return redirect()->route($step)->with('success', $msg);
        }
    
        return redirect()->route('home');
    }

}
