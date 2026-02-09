<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Models\ReportedMessage;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\GroupUse;

class GroupController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('groups.index', [
            'generalGroups' => $user->groups()
                ->withPivot('role', 'status', 'expired', 'last_read_message_id')
                ->where('group_type', 0)    // فیلتر بر اساس نوع گروه
                ->get()->reverse(),
            'specialityGroups' => $user->groups()
                ->withPivot('role', 'status', 'expired', 'last_read_message_id')
                ->whereNotNull('specialty_id')
                ->whereNull('experience_id')
                ->get()->reverse(),
            'experienceGroups' => $user->groups()
                ->withPivot('role', 'status', 'expired', 'last_read_message_id')
                ->whereNull('specialty_id')
                ->whereNotNull('experience_id')
                ->get()->reverse(),
            'ageGroups' => $user->groups()
                ->withPivot('role', 'status', 'expired', 'last_read_message_id')
                ->where('group_type', 3)
                ->get()->reverse(),
            'genderGroups' => $user->groups()
                ->withPivot('role', 'status', 'expired', 'last_read_message_id')
                ->where('group_type', 4)
                ->get()->reverse(),
            'managedGroups' => $user->groups()
                ->withPivot('role', 'status', 'expired', 'last_read_message_id')
                ->where('location_level', 10)  // Filter groups where user is a manager
                ->get(),
        ]);
        
    }

    public function show(Group $group)
    {
        // دریافت مدیران (role 3) و بازرسان (role 2)
        $admins = $group->users()
            ->withPivot(['role', 'status'])
            ->whereIn('role', [2, 3])
            ->wherePivot('status', 1)
            ->orderBy('role', 'desc') // مدیران اول (3)، سپس بازرسان (2)
            ->get();
        
        // دریافت آخرین فعالیت‌ها
        $recentMessages = $group->messages()->with('user')->latest()->take(5)->get();
        $recentPosts = $group->blogs()->with('user')->latest()->take(5)->get();
        $recentPolls = $group->polls()->with('user', 'options')->where('main_type', 1)->latest()->take(5)->get();
        $recentElections = $group->elections()->latest()->take(5)->get();
        
        // دریافت گروه‌های کاربر (به جز گروه فعلی)
        $user = auth()->user();
        
        // گروه‌های عمومی کاربر (به جز گروه فعلی)
        $userGeneralGroups = $user->groups()
            ->withPivot('role', 'status', 'expired', 'last_read_message_id')
            ->where('group_type', 0)
            ->where('groups.id', '!=', $group->id)
            ->wherePivot('status', 1) // فقط گروه‌های فعال
            ->orderBy('last_activity_at', 'desc')
            ->take(6)
            ->get();
        
        // گروه‌های تخصصی شغلی کاربر (specialty_id) - به جز گروه فعلی
        $userSpecialityGroups = $user->groups()
            ->withPivot('role', 'status', 'expired', 'last_read_message_id')
            ->whereNotNull('specialty_id')
            ->whereNull('experience_id')
            ->where('groups.id', '!=', $group->id)
            ->wherePivot('status', 1)
            ->orderBy('last_activity_at', 'desc')
            ->get();
        
        // گروه‌های تجربی/علمی کاربر (experience_id) - به جز گروه فعلی
        $userExperienceGroups = $user->groups()
            ->withPivot('role', 'status', 'expired', 'last_read_message_id')
            ->whereNull('specialty_id')
            ->whereNotNull('experience_id')
            ->where('groups.id', '!=', $group->id)
            ->wherePivot('status', 1)
            ->orderBy('last_activity_at', 'desc')
            ->get();
        
        // گروه‌های اختصاصی کاربر (location_level = 10) - به جز گروه فعلی
        $userExclusiveGroups = $user->groups()
            ->withPivot('role', 'status', 'expired', 'last_read_message_id')
            ->where('location_level', 10)
            ->where('groups.id', '!=', $group->id)
            ->wherePivot('status', 1)
            ->orderBy('last_activity_at', 'desc')
            ->get();
        
        // ترکیب گروه‌های تخصصی و اختصاصی برای نمایش در بخش "گروه‌های تخصصی پیشنهادی"
        $userSpecializedAndExclusiveGroups = $userSpecialityGroups
            ->merge($userExperienceGroups)
            ->merge($userExclusiveGroups)
            ->sortByDesc('last_activity_at')
            ->take(6)
            ->values();
        
        return view('groups.show', [
            'group' => $group,
            'messages' => $group->messages()->latest()->get(),
            'generalGroups' => $userGeneralGroups,
            'specializedGroups' => $userSpecializedAndExclusiveGroups,
            'exclusiveGroups' => $userExclusiveGroups,
            'admins' => $admins,
            'recentMessages' => $recentMessages,
            'recentPosts' => $recentPosts,
            'recentPolls' => $recentPolls,
            'recentElections' => $recentElections,
        ]);
    }

    public function logout(Group $group){
        $groupUserModel = GroupUser::where('group_id', $group->id)->where('user_id', auth()->user()->id)->first();
        
        if (!$groupUserModel) {
            return redirect()->route('groups.index')->with('error', 'رابطه کاربر و گروه یافت نشد');
        }
        
        $groupUserModel->update(['status' => 0]);

        return redirect()->route('groups.index')->with('success', 'شما با موفقیت از گروه خارج شدید');
    }

    public function relogout(Group $group){
        $groupUserModel = GroupUser::where('group_id', $group->id)->where('user_id', auth()->user()->id)->first();
        if (!$groupUserModel) {
            return redirect()->route('groups.index')->with('error', 'رابطه کاربر و گروه یافت نشد');
        }
        $groupUserModel->update(['status' => 1]);

        return redirect()->route('groups.index')->with('success', 'شما با موفقیت به گروه بازگشتید');
    }

    public function open(Group $group){
        if($group->is_open == 1){
            $group->is_open = 0;
            $msg = 'گروه با موفقیت به حالت بسته گردید';
        }else{
            $group->is_open = 1;
            $msg = 'گروه با موفقیت به حالت باز گردید';
        }
        $group->save();
        return back()->with('success', $msg);
    }

    public function update(Request $request, Group $group)
    {
        // Check if user is a manager
        $userRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->value('role');

        if ($userRole !== 3) {
            return back()->with('error', 'شما دسترسی لازم برای ویرایش گروه را ندارید.');
        }

        $inputs = $request->validate([
            'description' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');
            $filename = time() . '.' . $avatar->getClientOriginalExtension();
            $avatar->move(public_path('images/groups'), $filename);
            $inputs['avatar'] = $filename;
        }

        $group->update($inputs);

        return back()->with('success', 'اطلاعات گروه با موفقیت به‌روزرسانی شد.');
    }

public function addUsersToGroup(Request $request)
{
    $userInfo = $request->input('userInfo');
    $group = Group::find($request->input('groupId'));


// ابتدا جستجو بر اساس ترکیب first_name و last_name
    $user = User::where(DB::raw('CONCAT(first_name, " ", last_name)'),  $userInfo)
            ->first();

    if (!$user) {
        // اگر کاربر پیدا نشد، ادامه بررسی‌های دیگر
        if (is_numeric($userInfo)) {
            // بررسی شماره تلفن ایران
            $userInfo = preg_replace('/\D/', '', $userInfo); // حذف هر کاراکتر غیر عددی
                // جستجو بر اساس شماره تلفن ایران
            $user = User::where('phone', $userInfo)->orWhere('id', $userInfo)->first();

        } elseif (filter_var($userInfo, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $userInfo)->first();
        } elseif (preg_match('/^\+?(\d{1,3})?(\d{10})$/', $userInfo)) {
            // اعتبارسنجی شماره تلفن با فرمت‌های جهانی
            $userInfo = preg_replace('/\D/', '', $userInfo); // حذف کاراکترهای غیر عددی
            $user = User::where('phone', $userInfo)->first();
        }
    }

    $checkInGroup = GroupUser::where('group_id', $group->id)->where('user_id', $user->id)->first();

    if($checkInGroup){
        return response()->json(['message' => 'کاربر قبلا در گروه ثبت‌نام کرده است']);
    }

    $group->users()->attach($user->id, ['role' => '4', 'status' => 0, 'expired' => now()->addHours($request->input('hours'))]); // Adding as guest

    // Dispatch event for group invitation
    event(new \App\Events\GroupInvitation($group, $user, auth()->user()));

    return response()->json(['message' => 'Users added successfully']);
}

    /**
     * تغییر نقش کاربر بین ناظر (0) و فعال (1)
     * فقط برای مدیران (role 3)
     */
    public function toggleUserRole(Group $group, User $user)
    {
        // بررسی اینکه کاربر فعلی مدیر است
        $managerRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->value('role');
        
        if ($managerRole !== 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما دسترسی لازم را ندارید.'
            ], 403);
        }

        // بررسی اینکه کاربر در گروه است
        $groupUser = GroupUser::where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->first();

        if (!$groupUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'کاربر در این گروه یافت نشد.'
            ], 404);
        }

        // فقط بین ناظر (0) و فعال (1) تغییر می‌دهد
        if ($groupUser->role == 0) {
            $groupUser->role = 1;
            $newRole = 'فعال';
            $oldRole = 'ناظر';
        } elseif ($groupUser->role == 1) {
            $groupUser->role = 0;
            $newRole = 'ناظر';
            $oldRole = 'فعال';
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'فقط می‌توان نقش کاربران ناظر و فعال را تغییر داد.'
            ], 400);
        }

        $groupUser->save();

        return response()->json([
            'status' => 'success',
            'message' => "نقش کاربر {$user->fullName()} از {$oldRole} به {$newRole} تغییر پیدا کرد.",
            'new_role' => $groupUser->role,
            'new_role_label' => $newRole
        ]);
    }

    /**
     * دریافت لیست اعضای گروه برای مدیریت
     * فقط برای مدیران (role 3)
     */
    public function getMembers($group)
    {
        // اگر route model binding کار نکرد، گروه را دستی پیدا کن
        if (!($group instanceof Group)) {
            $group = Group::find($group);
            if (!$group) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'گروه یافت نشد.'
                ], 404);
            }
        }
        
        // بررسی اینکه کاربر فعلی مدیر است
        $managerRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->value('role');
        
        if ($managerRole !== 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما دسترسی لازم را ندارید.'
            ], 403);
        }

        $members = $group->users()
            ->wherePivotIn('role', [0, 1]) // فقط ناظر و فعال
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
            ->withPivot('role', 'status')
            ->orderBy('group_user.role', 'desc') // فعال‌ها اول
            ->orderBy('users.first_name', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->fullName(),
                    'email' => $user->email,
                    'role' => (int) $user->pivot->role,
                    'role_label' => $user->pivot->role == 0 ? 'ناظر' : 'فعال',
                    'status' => (int) $user->pivot->status,
                ];
            });

        return response()->json([
            'status' => 'success',
            'members' => $members
        ]);
    }

    public function getStats(Group $group)
    {
        // بررسی اینکه کاربر فعلی مدیر است
        $managerRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->value('role');
        
        if ($managerRole !== 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما دسترسی لازم را ندارید.'
            ], 403);
        }

        // آمار اعضا
        $membersStats = [
            'total' => $group->users()->count(),
            'active' => $group->users()->wherePivot('role', 1)->count(),
            'observer' => $group->users()->wherePivot('role', 0)->count(),
            'manager' => $group->users()->wherePivot('role', 3)->count(),
        ];

        // آمار پیام‌ها
        $messagesQuery = $group->messages();
        $messagesStats = [
            'total' => $messagesQuery->count(),
            'today' => $messagesQuery->whereDate('created_at', today())->count(),
            'this_week' => $messagesQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => $messagesQuery->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
        ];

        // آمار پست‌ها
        $postsQuery = $group->blogs();
        $postsStats = [
            'total' => $postsQuery->count(),
            'this_month' => $postsQuery->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
            'with_images' => $postsQuery->whereNotNull('image')->count(),
        ];

        // آمار نظرسنجی‌ها
        $pollsQuery = $group->polls()->where('main_type', 1);
        $pollsStats = [
            'total' => $pollsQuery->count(),
            'active' => $pollsQuery->where('end_time', '>', now())->count(),
            'expired' => $pollsQuery->where('end_time', '<=', now())->count(),
        ];

        // آمار انتخابات
        $electionsQuery = $group->polls()->where('main_type', 0);
        $electionsStats = [
            'total' => $electionsQuery->count(),
            'active' => $electionsQuery->where('end_time', '>', now())->count(),
            'closed' => $electionsQuery->where('end_time', '<=', now())->count(),
        ];

        // آمار گزارش‌ها
        $reportsQuery = \App\Models\ReportedMessage::where('group_id', $group->id);
        $reportsStats = [
            'pending' => $reportsQuery->where('status', 'pending_group_manager')->count(),
            'resolved' => $reportsQuery->where('status', 'resolved_by_group_manager')->count(),
            'escalated' => $reportsQuery->where('escalated_to_admin', true)->count(),
        ];

        // فعال‌ترین اعضا (بر اساس تعداد پیام‌ها)
        $mostActiveMembers = $group->users()
            ->select('users.id', 'users.first_name', 'users.last_name')
            ->withCount(['messages' => function ($query) use ($group) {
                $query->where('group_id', $group->id);
            }])
            ->orderBy('messages_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'name' => $user->fullName(),
                    'message_count' => $user->messages_count ?? 0,
                ];
            });

        return response()->json([
            'status' => 'success',
            'stats' => [
                'members' => $membersStats,
                'messages' => $messagesStats,
                'posts' => $postsStats,
                'polls' => $pollsStats,
                'elections' => $electionsStats,
                'reports' => $reportsStats,
                'most_active_members' => $mostActiveMembers,
            ]
        ]);
    }

}
