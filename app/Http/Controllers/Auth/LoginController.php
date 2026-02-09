<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Models\Address;
use App\Models\UserExperience;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Mail;
use App\Models\EmailVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;


class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(RedirectIfAuthenticated::class)->except('logout');
    }

    protected function redirectTo()
    {
        if (auth()->user()->national_id == null) {
            return route('register.step1');
        }

        if (!UserExperience::where('user_id', auth()->user()->id)->exists()) {
            return route('register.step2');
        }

        if (!Address::where('user_id', auth()->user()->id)->exists()) {
            return route('register.step3');
        }

        return '/home';
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        // ذخیره IP و تاریخ آخرین ورود
        $user->update([
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
        ]);
    }

    public function logout(Request $request)
    {
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        cache()->flush();
        
        return redirect('/')->with('clearLocalStorage', true);
    }
    
    public function forgotView(){
        return view('auth.reset-password.forgot');
    }
    
    public function resetView(){
        if(isset($_GET['email'])){
            $email =$_GET['email']; 
            return view('auth.reset-password.reset', compact('email'));
        }else{
            abort(404);
        }
    }
    
    public function forgot(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

                        $email = $request->email;
        $code = sprintf('%06d', random_int(0, 999999));

        // ذخیره کد در دیتابیس
        EmailVerification::updateOrCreate(
            ['email' => $email],
            [
                'code' => $code,
                'expires_at' => Carbon::now()->addMinutes(5)
            ]
        );
        
        // ارسال ایمیل
        $mail = Mail::send('emails.change-pass', ['code' => $code], function($message) use ($email) {
            $message->to($email)
                    ->subject('کد تغییر رمز عبور');
        });
        


        return redirect()->route('password.reset.viewForm', ['email' => $email])
                         ->with('success', 'کد بازیابی به ایمیل شما ارسال شد.');
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $code = $request->code;
                        $email = $request->email;


        $verification = EmailVerification::where('email', $email)
                                      ->where('code', $code)
                                      ->where('expires_at', '>', Carbon::now())
                                      ->first();
        if (!$verification) {
            return back()->with('error', 'کد وارد شده نامعتبر است.');
        }
        

        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $verification->delete();

        return redirect()->route('login')->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }

}
