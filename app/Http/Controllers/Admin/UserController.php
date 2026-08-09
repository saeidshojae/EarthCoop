<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;


class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with([
            'address.country',
            'address.province',
            'address.county',
            'address.section',
            'address.city',
            'address.rural',
            'address.region',
            'address.village',
            'address.neighborhood',
            'address.street',
            'address.alley',
            'occupationalFields',
            'experienceFields',
            'groups'
        ]);

        // فیلتر جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        // فیلتر وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فیلتر جنسیت
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        // فیلتر ایمیل تایید شده
        if ($request->filled('email_verified')) {
            if ($request->email_verified == '1') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // فیلتر استان
        if ($request->filled('province_id')) {
            $query->whereHas('address', function($q) use ($request) {
                $q->where('province_id', $request->province_id);
            });
        }

        // فیلتر تاریخ ثبت‌نام
        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        // آمار
        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'suspended' => User::where('status', 'suspended')->count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'today' => User::whereDate('created_at', today())->count(),
            'this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        // دریافت لیست استان‌ها برای فیلتر
        $provinces = \App\Models\Province::orderBy('name')->get();

        // داده‌های نمودار ثبت‌نام (30 روز گذشته)
        $registrationChartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = User::whereDate('created_at', $date->format('Y-m-d'))->count();
            $registrationChartData[] = [
                'date' => \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d'),
                'count' => $count
            ];
        }

        // داده‌های نمودار توزیع جغرافیایی (بر اساس استان)
        $geographicDistribution = \App\Models\User::whereHas('address', function($q) {
            $q->whereNotNull('province_id');
        })
        ->with('address.province')
        ->get()
        ->groupBy(function($user) {
            return $user->address && $user->address->province ? $user->address->province->name : 'نامشخص';
        })
        ->map(function($users) {
            return $users->count();
        })
        ->sortDesc()
        ->take(10); // 10 استان اول

        return view('admin.user.index', compact('users', 'stats', 'provinces', 'registrationChartData', 'geographicDistribution'));
    }

    public function create()
    {
        return view('admin.user.create');
    }
    
    public function store(Request $request){
        $inputs = $request->validate([
                        'email'    => 'required|email|unique:users,email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',

            'first_name'   => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'last_name'    => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'birth_date'   => 'required|array|min:3',
            'gender'       => 'required|in:male,female',
            'national_id'  => 'required|string|regex:/^\d{10}$/|unique:users,national_id',
            'phone'        => 'required|regex:/^(0)?9\d{9}$/|unique:users,phone',
            'password' => 'required|min:6|confirmed',
        ]);
        
        $nationalId = $this->convertNumbersToEnglish($inputs['national_id']);
        $phone = $this->normalizePhoneNumber($this->convertNumbersToEnglish($inputs['phone']));

        // اعتبارسنجی کد ملی
        if (!$this->isValidIranianNationalCode($nationalId)) {
            return back()->with('error', 'کد ملی وارد شده معتبر نیست')->withInput();
        }

    [$day, $month, $year] = array_map(
        fn($v) => $this->convertNumbersToEnglish($v),
        $inputs['birth_date']
    );


    $birthDateMiladi = (new \Morilog\Jalali\Jalalian((int)$year, (int)$month, (int)$day))->toCarbon();

    // بررسی سن
    if ($birthDateMiladi->age < 15) {
        return back()->with('error', 'سن شما باید حداقل ۱۵ سال باشد');
    }
    
    $inputs['birth_date'] = $birthDateMiladi->toDateString();


    $inputs['password'] = Hash::make($inputs['password']);
        User::create($inputs);
        return redirect()->route('admin.users.index')->with('success', 'کاربر با موفقیت ایجاد شد');
    }
    
    public function update(Request $request, User $user){
        $inputs = $request->validate([
                        'email'    => 'required|email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/|unique:users,email,' . $user->id,

            'first_name'   => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'last_name'    => 'required|string|max:50|regex:/^[\x{0600}-\x{06FF}\s]+$/u',
            'birth_date'   => 'required|array|min:3',
            'gender'       => 'required|in:male,female',
            'national_id'  => 'required|string|regex:/^\d{10}$/|unique:users,national_id,' . $user->id,
            'phone'        => 'required|regex:/^(0)?9\d{9}$/|unique:users,phone,' . $user->id,
            'password' => 'nullable|min:6|confirmed',
            'status' => 'nullable|in:active,inactive,suspended',
            'blocks' => 'nullable|array',
        ]);
        
        foreach(\App\Models\Block::where('user_id', $user->id)->get() as $block){
            $block->delete();
        }
        
        if(isset($inputs['blocks'])){
            foreach($inputs['blocks'] as $block){
                \App\Models\Block::create(['user_id' => $user->id, 'position' => $block]);
            }
        }
        
        $nationalId = $this->convertNumbersToEnglish($inputs['national_id']);
        $phone = $this->normalizePhoneNumber($this->convertNumbersToEnglish($inputs['phone']));

        // اعتبارسنجی کد ملی
        if (!$this->isValidIranianNationalCode($nationalId)) {
            return back()->with('error', 'کد ملی وارد شده معتبر نیست')->withInput();
        }

    [$day, $month, $year] = array_map(
        fn($v) => $this->convertNumbersToEnglish($v),
        $inputs['birth_date']
    );


    $birthDateMiladi = (new \Morilog\Jalali\Jalalian((int)$year, (int)$month, (int)$day))->toCarbon();

    // بررسی سن
    if ($birthDateMiladi->age < 15) {
        return back()->with('error', 'سن شما باید حداقل ۱۵ سال باشد');
    }
    
    $inputs['birth_date'] = $birthDateMiladi->toDateString();

    
    if($inputs['password'] != null){
         $inputs['password'] = Hash::make($inputs['password']);
    }else{
        unset($inputs['password']);
    }
    
    // Set default status if not provided
    if (!isset($inputs['status'])) {
        $inputs['status'] = 'active';
    }
    
    $user->update($inputs);
            return redirect()->route('admin.users.index')->with('success', 'کاربر با موفقیت بروزرسانی شد');

   
    }
    public function edit(User $user)
    {
        return view('admin.user.edit', compact('user'));
    }
    
    
    public function show(User $user)
    {
        $user->load([
            'address.country',
            'address.province',
            'address.county',
            'address.section',
            'address.city',
            'address.rural',
            'address.region',
            'address.village',
            'address.neighborhood',
            'address.street',
            'address.alley',
            'occupationalFields',
            'experienceFields',
            'groups',
            'roles.permissions'
        ]);

        // آمار کاربر
        $userStats = [
            'groups_count' => $user->groups->count(),
            'blog_posts_count' => \App\Models\Blog::where('user_id', $user->id)->count(), // پست‌های گروهی
            'website_posts_count' => \App\Modules\Blog\Models\Post::where('user_id', $user->id)->count(), // پست‌های وبلاگ
            'blog_comments_count' => \App\Models\Comment::where('user_id', $user->id)->count(), // نظرات پست‌های گروهی
            'website_comments_count' => \App\Modules\Blog\Models\BlogComment::where('user_id', $user->id)->count(), // نظرات وبلاگ
            'total_posts_count' => \App\Models\Blog::where('user_id', $user->id)->count() + \App\Modules\Blog\Models\Post::where('user_id', $user->id)->count(),
            'total_comments_count' => \App\Models\Comment::where('user_id', $user->id)->count() + \App\Modules\Blog\Models\BlogComment::where('user_id', $user->id)->count(),
        ];

        // دریافت همه نقش‌ها برای اختصاص
        $allRoles = \App\Models\Role::orderBy('order')->get();

        // دریافت Session های فعال کاربر
        $activeSessions = [];
        try {
            $sessions = \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $user->id)
                ->get();
            
            foreach ($sessions as $session) {
                $payload = unserialize(base64_decode($session->payload));
                $activeSessions[] = [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => \Carbon\Carbon::createFromTimestamp($session->last_activity),
                ];
            }
        } catch (\Exception $e) {
            // اگر جدول sessions وجود نداشت، خطا نده
        }

        return view('admin.user.show', compact('user', 'userStats', 'allRoles', 'activeSessions'));
    }

    // نمایش لیست تراکنش‌های امتیاز کاربر (ledger) در پنل ادمین
    public function transactions(Request $request, User $user)
    {
        $query = \App\Models\UserPointTransaction::where('user_id', $user->id);

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->orderByDesc('created_at')->paginate(25)->appends($request->except('page'));

        $currentPoints = optional(\App\Models\UserPoint::where('user_id', $user->id)->first())->points ?? 0;

        return view('admin.user.transactions', compact('user', 'transactions', 'currentPoints'));
    }

    // اختصاص نقش به کاربر
    public function assignRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user->roles()->sync($validated['role_ids']);

        return back()->with('success', 'نقش‌های کاربر با موفقیت بروزرسانی شد');
    }

    // نمایش صفحه Import
    public function showImport()
    {
        return view('admin.user.import');
    }

    // Import کاربران از Excel/CSV
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // حداکثر 10MB
        ]);

        try {
            $file = $request->file('file');
            $data = Excel::load($file)->get();

            $errors = [];
            $successCount = 0;
            $skipCount = 0;

            foreach ($data as $row) {
                // تبدیل به آرایه
                $rowData = $row->toArray();
                
                // اعتبارسنجی داده‌ها
                $validator = Validator::make($rowData, [
                    'email' => 'required|email|unique:users,email',
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'phone' => 'nullable|string|max:20',
                    'national_id' => 'nullable|string|max:20',
                    'gender' => 'nullable|in:male,female',
                    'password' => 'nullable|string|min:8',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowData,
                        'errors' => $validator->errors()->all()
                    ];
                    $skipCount++;
                    continue;
                }

                // ایجاد کاربر
                $user = User::create([
                    'email' => $rowData['email'],
                    'first_name' => $rowData['first_name'],
                    'last_name' => $rowData['last_name'],
                    'phone' => $rowData['phone'] ?? null,
                    'national_id' => $rowData['national_id'] ?? null,
                    'gender' => $rowData['gender'] ?? null,
                    'password' => Hash::make($rowData['password'] ?? 'password123'), // رمز پیش‌فرض
                    'status' => $rowData['status'] ?? 'active',
                    'email_verified_at' => isset($rowData['email_verified']) && $rowData['email_verified'] == '1' ? now() : null,
                ]);

                $successCount++;
            }

            $message = "Import با موفقیت انجام شد. {$successCount} کاربر ایجاد شد.";
            if ($skipCount > 0) {
                $message .= " {$skipCount} ردیف رد شد.";
            }

            return back()->with('success', $message)->with('import_errors', $errors);

        } catch (\Exception $e) {
            return back()->with('error', 'خطا در Import: ' . $e->getMessage());
        }
    }

    // نمایش صفحه ارسال پیام/ایمیل
    public function showSendMessage(User $user)
    {
        return view('admin.user.send-message', compact('user'));
    }

    // ارسال پیام/ایمیل به کاربر
    public function sendMessage(Request $request, User $user)
    {
        $validated = $request->validate([
            'type' => 'required|in:email,notification',
            'subject' => 'required_if:type,email|string|max:255',
            'message' => 'required|string',
        ]);

        try {
            if ($validated['type'] == 'email') {
                // ارسال ایمیل
                \Illuminate\Support\Facades\Mail::raw($validated['message'], function ($mail) use ($user, $validated) {
                    $mail->to($user->email)
                         ->subject($validated['subject']);
                });
                
                return back()->with('success', 'ایمیل با موفقیت ارسال شد');
            } else {
                // ارسال اعلان
                $user->notify(new \App\Notifications\AdminMessage($validated['message']));
                
                return back()->with('success', 'پیام با موفقیت ارسال شد');
            }
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در ارسال: ' . $e->getMessage());
        }
    }

    // Force Logout (خروج اجباری کاربر)
    public function forceLogout(User $user)
    {
        try {
            // حذف همه session های کاربر
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
            
            return back()->with('success', 'کاربر با موفقیت از همه دستگاه‌ها خارج شد');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در خروج اجباری: ' . $e->getMessage());
        }
    }
    
    public function destroy(User $user)
    {
        throw new \LogicException('Direct admin user deletion is retired. Resolve the safe admin controller boundary.');
    }

    // تغییر وضعیت کاربر
    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,suspended'
        ]);

        $user->update(['status' => $request->status]);
        
        return back()->with('success', 'وضعیت کاربر با موفقیت تغییر کرد');
    }

    // عملیات دسته‌ای
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,suspend,delete,export',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $userIds = $request->user_ids;

        switch ($request->action) {
            case 'activate':
                User::whereIn('id', $userIds)->update(['status' => 'active']);
                return back()->with('success', count($userIds) . ' کاربر فعال شدند');
            
            case 'deactivate':
                User::whereIn('id', $userIds)->update(['status' => 'inactive']);
                return back()->with('success', count($userIds) . ' کاربر غیرفعال شدند');
            
            case 'suspend':
                User::whereIn('id', $userIds)->update(['status' => 'suspended']);
                return back()->with('success', count($userIds) . ' کاربر تعلیق شدند');
            
            case 'delete':
                throw new \LogicException('Direct admin bulk deletion is retired. Resolve the safe admin controller boundary.');
            
            case 'export':
                return $this->exportUsers($userIds);
        }
    }

    // Export به Excel
    public function exportUsers($userIds = null)
    {
        $query = User::with([
            'address.country',
            'address.province',
            'address.county',
            'address.section',
            'address.city',
            'address.rural',
            'address.region',
            'address.village',
            'address.neighborhood',
            'address.street',
            'address.alley',
            'occupationalFields',
            'experienceFields'
        ]);

        if ($userIds) {
            $query->whereIn('id', $userIds);
        }

        $users = $query->get();

        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');
            
            // BOM برای UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // هدرها
            fputcsv($file, [
                'نام', 'نام خانوادگی', 'ایمیل', 'شماره تماس', 'کد ملی',
                'جنسیت', 'تاریخ تولد', 'وضعیت', 'ایمیل تایید شده',
                'کشور', 'استان', 'شهرستان', 'بخش', 'شهر/روستا',
                'منطقه/دهستان', 'محله', 'خیابان', 'کوچه',
                'صنف', 'تخصص', 'تاریخ ثبت‌نام'
            ]);

            // داده‌ها
            foreach ($users as $user) {
                fputcsv($file, [
                    $user->first_name,
                    $user->last_name,
                    $user->email,
                    $user->phone,
                    $user->national_id,
                    $user->gender == 'male' ? 'مرد' : 'زن',
                    $user->birth_date,
                    $user->status ?? 'active',
                    $user->email_verified_at ? 'بله' : 'خیر',
                    $user->address?->country?->name ?? '',
                    $user->address?->province?->name ?? '',
                    $user->address?->county?->name ?? '',
                    $user->address?->section?->name ?? '',
                    $user->address?->city?->name ?? $user->address?->rural?->name ?? '',
                    $user->address?->region?->name ?? $user->address?->village?->name ?? '',
                    $user->address?->neighborhood?->name ?? '',
                    $user->address?->street?->name ?? '',
                    $user->address?->alley?->name ?? '',
                    $user->occupationalFields->pluck('name')->implode(', '),
                    $user->experienceFields->pluck('name')->implode(', '),
                    $user->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Reset Password
    public function resetPassword(User $user)
    {
        $newPassword = \Str::random(12);
        $user->update(['password' => Hash::make($newPassword)]);
        
        return back()->with('success', 'رمز عبور جدید: ' . $newPassword);
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

    protected function convertNumbersToEnglish($string) {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];
    
        $string = str_replace($persian, $english, $string);
        return str_replace($arabic, $english, $string);
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
    
}