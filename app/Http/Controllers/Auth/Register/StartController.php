<?php
namespace App\Http\Controllers\Auth\Register;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Models\Group;
use App\Models\Address;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\InvitationCode;
use App\Http\Controllers\Auth\EmailVerificationController;

class StartController extends Controller
{
    public function showWelcome()
    {
        if(auth()->check()){
            return redirect()->route('home');
            exit;
        }

        // محاسبه آمار پویا
        $membersCount = User::count();
        
        // تعداد پروژه‌ها: پست‌هایی که دسته‌بندی آن‌ها "پروژه" است
        $projectCategory = Category::where('name', 'پروژه')->first();
        $projectsCount = $projectCategory ? Blog::where('category_id', $projectCategory->id)->count() : 0;
        
        $countriesCount = DB::table('addresses')
            ->whereNotNull('country_id')
            ->distinct()
            ->count('country_id');

        $stats = [
            'members_count' => $membersCount,
            'projects_count' => $projectsCount,
            'countries_count' => $countriesCount,
            'members_formatted' => format_number($membersCount),
            'projects_formatted' => format_number($projectsCount),
            'countries_formatted' => format_number($countriesCount, 0),
        ];

        // دریافت نظرات واقعی برای بخش testimonials
        $testimonials = Comment::whereNull('parent_id') // فقط نظرات اصلی (نه پاسخ‌ها)
            ->whereRaw('CHAR_LENGTH(message) >= 80') // نظرات با طول مناسب
            ->with(['user.occupationalFields', 'user.address.city', 'user.address.province', 'user.address.country'])
            ->whereHas('user', function($q) {
                $q->whereNotNull('first_name')
                  ->whereNotNull('last_name');
            })
            ->orderByDesc('created_at')
            ->limit(10) // بیشتر بگیریم تا بتوانیم بهترین‌ها را انتخاب کنیم
            ->get()
            ->map(function($comment) {
                $user = $comment->user;
                $occupationalField = $user->occupationalFields->first();
                
                // ساخت مکان از Address اگر موجود باشد
                $locationText = '';
                if ($user->address) {
                    $address = $user->address;
                    if ($address->city) {
                        $locationText = $address->city->name;
                        if ($address->province) {
                            $locationText .= '، ' . $address->province->name;
                        }
                    } elseif ($address->province) {
                        $locationText = $address->province->name;
                    } elseif ($address->country) {
                        $locationText = $address->country->name;
                    }
                }
                
                return [
                    'quote' => $comment->message,
                    'name' => $user->fullName(),
                    'role' => $occupationalField ? $occupationalField->name : 'عضو EarthCoop',
                    'location' => $locationText,
                    'avatar' => $user->avatar ? asset('images/users/avatars/' . $user->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($user->fullName()) . '&background=10b981&color=fff&size=250',
                ];
            })
            ->take(3); // فقط 3 تا اول را بگیریم

        // اگر نظرات کافی نبود، از داده‌های تستی استفاده کنیم
        if ($testimonials->count() < 3) {
            // می‌توانیم نظرات تستی را هم اضافه کنیم یا فقط همان‌هایی که داریم را نمایش دهیم
        }

        return view('welcome', compact('stats', 'testimonials'));
    }

    public function processAgreement(Request $request)
    {
        $code = '';
        $setting = Setting::find(1);
        
        // Validate terms acceptance first
        $request->validate([
            'terms' => 'required|accepted'
        ], [
            'terms.required' => 'لطفاً قوانین و مقررات را بپذیرید',
            'terms.accepted' => 'لطفاً قوانین و مقررات را بپذیرید'
        ]);
        
        if($setting->invation_status == 0){
            $request->validate([
                'invite_code' => 'nullable|string'
            ]);    
        }else{
            $inputs = $request->validate([
                'invite_code' => 'required|string|exists:invitation_codes,code'
            ], [
                'invite_code.required' => 'لطفاً کد دعوت خود را وارد کنید',
                'invite_code.exists' => 'کد دعوت وارد شده در سیستم وجود ندارد'
            ]);
            
            $invartion = InvitationCode::where('code', $inputs['invite_code'])->where('used', 0)->where('expire_at', '>=', now())->first();
            if($invartion == null){
                return redirect()->back()->withErrors(['invite_code' => 'کد دعوت وارد شده نامعتبر، استفاده شده یا منقضی شده است'])->withInput();
            }else{
                $code = $invartion->code;
            }
        }

        session(['fingerprint_id' => $request->fingerprint_id]);
        return redirect()->route('register.form', ['code' => $code]);
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function processRegister(Request $request)
    {
        $inputs = $request->validate([
            'email'    => 'required|email|unique:users,email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'phone'    => 'nullable|unique:users,phone',
            'password' => 'required|min:6|confirmed',
            'invation_code' => 'nullable|string',
        ]);

        $user = User::create([
            'email'          => $request->email,
            'phone'          => $request->phone,
            'password'       => Hash::make($request->password),
            'fingerprint_id' => session('fingerprint_id'),
        ]);
        
        if(isset($inputs['invation_code'])){
            $invartion = InvitationCode::where('code', $inputs['invation_code'])->where('used', 0)->where('expire_at', '>=', now())->first();
            if($invartion != null){
                $invartion->update([
                    'used' => 1 ,
                    'used_by' => $user->id,
                    'used_at' => now(),
                ]);
            }
        }

        // ارسال کد تأیید ایمیل
        $emailVerification = new EmailVerificationController();
        $emailVerification->sendVerificationCode($request);

        auth()->login($user);
        return redirect()->route('email.verify.form', ['email' => $request->email]);
    }
}
