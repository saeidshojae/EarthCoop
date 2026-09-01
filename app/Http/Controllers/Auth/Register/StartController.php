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
use Illuminate\Support\Facades\Schema;
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
        $membersCount = User::members()->count();

        // تعداد پروژه‌ها: وقتی schema کامل نیست از خطای SQL جلوگیری می‌کنیم.
        $projectsCount = 0;
        if (Schema::hasTable('categories') && Schema::hasTable('blogs')) {
            $projectCategory = DB::table('categories')->where('name', 'پروژه')->first();
            if ($projectCategory) {
                $projectsCount = Blog::where('category_id', $projectCategory->id)->count();
            }
        } elseif (Schema::hasTable('blog_categories') && Schema::hasTable('blogs')) {
            $projectCategory = DB::table('blog_categories')->where('name', 'پروژه')->first();
            if ($projectCategory) {
                $projectsCount = Blog::where('category_id', $projectCategory->id)->count();
            }
        }

        $countriesCount = 0;
        if (Schema::hasTable('addresses')) {
            $countriesCount = DB::table('addresses')
                ->whereNotNull('country_id')
                ->distinct()
                ->count('country_id');
        }

        $stats = [
            'members_count' => $membersCount,
            'projects_count' => $projectsCount,
            'countries_count' => $countriesCount,
            'members_formatted' => format_number($membersCount),
            'projects_formatted' => format_number($projectsCount),
            'countries_formatted' => format_number($countriesCount, 0),
        ];

        // دریافت نظرات واقعی برای بخش testimonials
        $testimonials = collect();
        if (Schema::hasTable('comments')) {
            try {
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
            } catch (\Throwable $e) {
                $testimonials = collect();
            }
        }

        // اگر نظرات کافی نبود، از داده‌های تستی استفاده کنیم
        if ($testimonials->count() < 3) {
            // می‌توانیم نظرات تستی را هم اضافه کنیم یا فقط همان‌هایی که داریم را نمایش دهیم
        }

        return view('welcome', compact('stats', 'testimonials'));
    }

    public function processAgreement(Request $request)
    {
        $setting = Setting::find(1);
        $invitationRequired = $setting && (int) $setting->invation_status === 1;
        
        // Validate terms acceptance first
        $request->validate([
            'terms' => 'required|accepted'
        ], [
            'terms.required' => 'لطفاً قوانین و مقررات را بپذیرید',
            'terms.accepted' => 'لطفاً قوانین و مقررات را بپذیرید'
        ]);
        
        if ($invitationRequired) {
            $inputs = $request->validate([
                'invite_code' => 'required|string|exists:invitation_codes,code'
            ], [
                'invite_code.required' => 'لطفاً کد دعوت خود را وارد کنید',
                'invite_code.exists' => 'کد دعوت وارد شده در سیستم وجود ندارد'
            ]);
            
            $invartion = InvitationCode::where('code', $inputs['invite_code'])->where('used', 0)->where('expire_at', '>=', now())->first();
            if ($invartion == null) {
                return redirect()->back()->withErrors(['invite_code' => 'کد دعوت وارد شده نامعتبر، استفاده شده یا منقضی شده است'])->withInput();
            }

            $request->session()->put('registration_invitation_code', $invartion->code);
        } else {
            $request->session()->forget('registration_invitation_code');
        }

        session([
            'fingerprint_id' => $request->fingerprint_id,
            'registration_terms_accepted' => true,
            'registration_terms_accepted_at' => now()->toIso8601String(),
        ]);
        return redirect()->route('register.form');
    }

    public function showRegisterForm(Request $request)
    {
        if ($request->session()->get('registration_terms_accepted') !== true) {
            return redirect()->route('welcome')->withErrors([
                'terms' => 'برای ورود به صفحه ثبت‌نام، ابتدا اساسنامه و شرایط استفاده را بپذیرید.',
            ]);
        }

        $setting = Setting::find(1);
        $invitationRequired = $setting && (int) $setting->invation_status === 1;
        $invitationCode = $invitationRequired
            ? $request->session()->get('registration_invitation_code')
            : null;

        if ($invitationRequired) {
            $validInvitation = InvitationCode::where('code', $invitationCode)
                ->where('used', 0)
                ->where('expire_at', '>=', now())
                ->exists();

            if (!$validInvitation) {
                return redirect()->route('welcome')->withErrors([
                    'invite_code' => 'برای ثبت‌نام باید یک کد دعوت معتبر وارد کنید.',
                ]);
            }
        } else {
            $request->session()->forget('registration_invitation_code');
        }

        return view('auth.register', compact('invitationRequired', 'invitationCode'));
    }

    public function processRegister(Request $request)
    {
        if ($request->session()->get('registration_terms_accepted') !== true) {
            return redirect()->route('welcome')->withErrors([
                'terms' => 'برای ثبت‌نام، ابتدا اساسنامه و شرایط استفاده را بپذیرید.',
            ]);
        }

        $setting = Setting::find(1);
        $invitationRequired = $setting && (int) $setting->invation_status === 1;
        $invitationCode = $invitationRequired
            ? $request->session()->get('registration_invitation_code')
            : null;

        if (!$invitationRequired) {
            $request->session()->forget('registration_invitation_code');
        }

        $request->validate([
            'email'    => 'required|email|unique:users,email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'phone'    => 'nullable|unique:users,phone',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = DB::transaction(function () use ($request, $invitationRequired, $invitationCode) {
            $invitation = null;

            if ($invitationRequired) {
                // The lock makes the final validity check + claim a single atomic
                // operation. Two concurrent registrations cannot consume one code.
                $invitation = InvitationCode::where('code', $invitationCode)
                    ->lockForUpdate()
                    ->first();

                if (!$invitation
                    || $invitation->used
                    || !$invitation->expire_at
                    || $invitation->expire_at->lt(now())) {
                    return null;
                }
            }

            $newUser = User::create([
                'email'          => $request->email,
                'phone'          => $request->phone,
                'password'       => Hash::make($request->password),
                'fingerprint_id' => session('fingerprint_id'),
                'terms_accepted_at' => now(),
            ]);

            if ($invitation) {
                $invitation->forceFill([
                    'used' => true,
                    'used_by' => $newUser->id,
                    'used_at' => now(),
                ])->save();
            }

            return $newUser;
        });

        if (!$user) {
            $request->session()->forget('registration_invitation_code');

            return redirect()->route('welcome')->withErrors([
                'invite_code' => 'کد دعوت معتبر نیست، منقضی شده یا هم‌زمان توسط عضو دیگری استفاده شده است. لطفاً کد دعوت دیگری وارد کنید.',
            ]);
        }

        // ارسال کد تأیید ایمیل
        $emailVerification = new EmailVerificationController();
        $emailVerification->sendVerificationCode($request);

        auth()->login($user);
        $request->session()->forget([
            'registration_terms_accepted',
            'registration_terms_accepted_at',
            'registration_invitation_code',
        ]);
        return redirect()->route('email.verify.form', ['email' => $request->email]);
    }
}
