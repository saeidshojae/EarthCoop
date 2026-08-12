<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupConfig;
use App\Models\User;
use App\Models\ReportedMessage;
use App\Services\NajmHoda\NajmHodaGroupAssistantService;
use App\Services\GroupChat\GroupSessionService;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\GroupUse;

class GroupController extends Controller
{
    protected function hasGroupLeadershipAccess(Group $group): bool
    {
        $role = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->where('status', 1)
            ->value('role');

        return in_array((int) $role, [2, 3], true);
    }

    protected function getGroupAssistantGlobalPolicy(): array
    {
        return [
            'assistant_enabled' => (bool) config('najm-hoda.group_assistant.enabled', true),
            'meeting_mode_enabled' => (bool) config('najm-hoda.group_assistant.meeting_mode_enabled', true),
            'allow_proactive_guidance' => (bool) config('najm-hoda.group_assistant.allow_proactive_guidance', true),
            'allow_private_messages' => (bool) config('najm-hoda.group_assistant.allow_private_messages', true),
            'private_message_mode' => (string) config('najm-hoda.group_assistant.private_message_mode', 'direct'),
            'action_executor_enabled' => (bool) config('najm-hoda.group_assistant.action_executor.enabled', true),
            'action_propose_before_execute' => (bool) config('najm-hoda.group_assistant.action_executor.propose_before_execute', false),
            'action_allow_create_post' => (bool) config('najm-hoda.group_assistant.action_executor.allow_create_post', true),
            'action_allow_create_poll' => (bool) config('najm-hoda.group_assistant.action_executor.allow_create_poll', true),
            'action_allow_create_comment' => (bool) config('najm-hoda.group_assistant.action_executor.allow_create_comment', true),
            'action_allow_react_message' => (bool) config('najm-hoda.group_assistant.action_executor.allow_react_message', true),
            'action_allow_react_post' => (bool) config('najm-hoda.group_assistant.action_executor.allow_react_post', true),
            'action_allow_react_comment' => (bool) config('najm-hoda.group_assistant.action_executor.allow_react_comment', true),
            'action_max_per_hour' => (int) config('najm-hoda.group_assistant.action_executor.max_actions_per_hour', 6),
            'max_replies_per_hour' => (int) config('najm-hoda.group_assistant.max_replies_per_hour', 12),
            'min_reply_interval_seconds' => (int) config('najm-hoda.group_assistant.min_reply_interval_seconds', 90),
            'auto_reply_mode' => (string) config('najm-hoda.group_assistant.auto_reply_mode', 'mention_or_question'),
            'knowledge_scope' => (string) config('najm-hoda.group_assistant.knowledge_scope', 'hybrid'),
            'default_agent' => (string) config('najm-hoda.group_assistant.default_agent', 'steward'),
        ];
    }

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

    public function open(Request $request, Group $group, GroupSessionService $sessions)
    {
        $this->authorize('manageSession', $group);
        if (! (bool) $group->is_open) {
            $session = $sessions->end($group, (int) auth()->id());
            $message = 'جلسه پایان یافت و مشارکت عمومی دوباره فعال شد.';
        } else {
            $validated = $request->validate([
                'title' => ['nullable', 'string', 'max:160'],
                'subject' => ['nullable', 'string', 'max:1000'],
                'agenda' => ['nullable', 'string', 'max:3000'],
                'starts_at' => ['nullable', 'date'],
            ]);
            $startsAt = isset($validated['starts_at']) ? now()->parse($validated['starts_at']) : now();
            $session = GroupSession::create([
                'group_id' => $group->id, 'created_by' => auth()->id(),
                'title' => trim($validated['title'] ?? '') ?: 'نشست گروه ' . $group->name,
                'subject' => $validated['subject'] ?? null, 'agenda' => $validated['agenda'] ?? null,
                'starts_at' => $startsAt, 'status' => 'scheduled',
            ]);
            if ($startsAt->isFuture()) {
                $sessions->scheduled($session, (int) auth()->id());
                $message = 'جلسه برای زمان تعیین‌شده برنامه‌ریزی شد.';
            } else {
                $session = $sessions->start($session, (int) auth()->id());
                $message = 'جلسه آغاز شد و مشارکت عمومی محدود شد.';
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => $message, 'session' => $session ? $sessions->payload($session) : null]);
        }
        return back()->with('success', $message);
    }

    public function toggleSessionPermission(Group $group, User $user)
    {
        $this->authorize('manageSession', $group);

        $membership = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->firstOrFail();

        abort_if(in_array((int) $membership->role, [2, 3], true), 422, 'مدیران و بازرسان به‌صورت پیش‌فرض مجاز هستند.');
        $membership->update(['session_write_allowed' => ! (bool) $membership->session_write_allowed]);

        return back()->with('success', $membership->session_write_allowed
            ? "مجوز مشارکت در نشست بسته برای {$user->fullName()} فعال شد."
            : "مجوز مشارکت در نشست بسته برای {$user->fullName()} لغو شد.");
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
            ->wherePivotIn('role', [0, 1, 3]) // ناظر، فعال و مدیر
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
                    'role_label' => match ((int) $user->pivot->role) {
                        3 => 'مدیر',
                        1 => 'فعال',
                        default => 'ناظر',
                    },
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

    public function najmHodaSettings(Group $group, NajmHodaGroupAssistantService $assistantService)
    {
        if (!$this->hasGroupLeadershipAccess($group)) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما دسترسی لازم را ندارید.'
            ], 403);
        }

        $assistantService->ensureGroupAssistantSetup($group);

        $config = NajmHodaGroupConfig::where('group_id', $group->id)->first();
        if (!$config) {
            return response()->json([
                'status' => 'error',
                'message' => 'تنظیمات نجم‌هدا یافت نشد.'
            ], 404);
        }

        $stats = [
            'action_items_total' => NajmHodaGroupActionItem::where('group_id', $group->id)->count(),
            'action_items_open' => NajmHodaGroupActionItem::where('group_id', $group->id)->where('status', 'open')->count(),
            'action_items_done' => NajmHodaGroupActionItem::where('group_id', $group->id)->where('status', 'done')->count(),
            'action_items_overdue' => NajmHodaGroupActionItem::where('group_id', $group->id)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];

        $global = $this->getGroupAssistantGlobalPolicy();
        $settings = [
            'enabled' => $global['assistant_enabled'] ? (bool) $config->enabled : false,
            'assistant_role' => (string) $config->assistant_role,
            'meeting_mode_enabled' => $global['meeting_mode_enabled'] ? (bool) $config->meeting_mode_enabled : false,
            'allow_proactive_guidance' => $global['allow_proactive_guidance'] ? (bool) $config->allow_proactive_guidance : false,
            'allow_private_messages' => $global['allow_private_messages']
                ? (bool) data_get($config->policy, 'allow_private_messages', true)
                : false,
            'private_message_mode' => (string) data_get($config->policy, 'private_message_mode', $global['private_message_mode']),
            'action_propose_before_execute' => $global['action_executor_enabled'] && $global['action_propose_before_execute']
                ? (bool) data_get($config->policy, 'action_executor.propose_before_execute', false)
                : false,
            'action_allow_create_post' => $global['action_executor_enabled'] && $global['action_allow_create_post']
                ? (bool) data_get($config->policy, 'action_executor.allow_create_post', true)
                : false,
            'action_allow_create_poll' => $global['action_executor_enabled'] && $global['action_allow_create_poll']
                ? (bool) data_get($config->policy, 'action_executor.allow_create_poll', true)
                : false,
            'action_allow_create_comment' => $global['action_executor_enabled'] && $global['action_allow_create_comment']
                ? (bool) data_get($config->policy, 'action_executor.allow_create_comment', true)
                : false,
            'action_allow_react_message' => $global['action_executor_enabled'] && $global['action_allow_react_message']
                ? (bool) data_get($config->policy, 'action_executor.allow_react_message', true)
                : false,
            'action_allow_react_post' => $global['action_executor_enabled'] && $global['action_allow_react_post']
                ? (bool) data_get($config->policy, 'action_executor.allow_react_post', true)
                : false,
            'action_allow_react_comment' => $global['action_executor_enabled'] && $global['action_allow_react_comment']
                ? (bool) data_get($config->policy, 'action_executor.allow_react_comment', true)
                : false,
        ];

        return response()->json([
            'status' => 'success',
            'settings' => $settings,
            'global' => $global,
            'stats' => $stats,
        ]);
    }

    public function najmHodaGuide(Group $group, NajmHodaGroupAssistantService $assistantService)
    {
        if (!$this->hasGroupLeadershipAccess($group)) {
            abort(403);
        }

        $assistantService->ensureGroupAssistantSetup($group);
        $config = NajmHodaGroupConfig::where('group_id', $group->id)->first();

        return view('groups.najm-hoda-guide', [
            'group' => $group,
            'config' => $config,
        ]);
    }

    public function najmHodaPanel(Group $group, NajmHodaGroupAssistantService $assistantService)
    {
        if (!$this->hasGroupLeadershipAccess($group)) {
            abort(403);
        }

        $assistantService->ensureGroupAssistantSetup($group);
        $config = NajmHodaGroupConfig::where('group_id', $group->id)->first();

        return view('groups.najm-hoda-panel', [
            'group' => $group,
            'config' => $config,
        ]);
    }

    public function updateNajmHodaSettings(Request $request, Group $group, NajmHodaGroupAssistantService $assistantService)
    {
        if (!$this->hasGroupLeadershipAccess($group)) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما دسترسی لازم را ندارید.'
            ], 403);
        }

        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'assistant_role' => 'nullable|in:secretary,advisor,admin,hybrid',
            'meeting_mode_enabled' => 'nullable|boolean',
            'allow_proactive_guidance' => 'nullable|boolean',
            'allow_private_messages' => 'nullable|boolean',
            'private_message_mode' => 'nullable|in:direct,request',
            'action_propose_before_execute' => 'nullable|boolean',
            'action_allow_create_post' => 'nullable|boolean',
            'action_allow_create_poll' => 'nullable|boolean',
            'action_allow_create_comment' => 'nullable|boolean',
            'action_allow_react_message' => 'nullable|boolean',
            'action_allow_react_post' => 'nullable|boolean',
            'action_allow_react_comment' => 'nullable|boolean',
        ]);

        $assistantService->ensureGroupAssistantSetup($group);
        $config = NajmHodaGroupConfig::where('group_id', $group->id)->first();
        if (!$config) {
            return response()->json([
                'status' => 'error',
                'message' => 'تنظیمات نجم‌هدا یافت نشد.'
            ], 404);
        }

        $global = $this->getGroupAssistantGlobalPolicy();
        $policy = is_array($config->policy) ? $config->policy : [];

        if (!$global['assistant_enabled']) {
            $config->enabled = false;
        } elseif (array_key_exists('enabled', $validated)) {
            $config->enabled = (bool) $validated['enabled'];
            unset($validated['enabled']);
        }
        if (!$global['meeting_mode_enabled']) {
            $config->meeting_mode_enabled = false;
        }
        if (array_key_exists('meeting_mode_enabled', $validated)) {
            $config->meeting_mode_enabled = $global['meeting_mode_enabled']
                ? (bool) $validated['meeting_mode_enabled']
                : false;
            unset($validated['meeting_mode_enabled']);
        }
        if (!$global['allow_proactive_guidance']) {
            $config->allow_proactive_guidance = false;
        }
        if (array_key_exists('allow_proactive_guidance', $validated)) {
            $config->allow_proactive_guidance = $global['allow_proactive_guidance']
                ? (bool) $validated['allow_proactive_guidance']
                : false;
            unset($validated['allow_proactive_guidance']);
        }

        if (!$global['allow_private_messages']) {
            $policy['allow_private_messages'] = false;
        }
        if (array_key_exists('allow_private_messages', $validated)) {
            $policy['allow_private_messages'] = $global['allow_private_messages']
                ? (bool) $validated['allow_private_messages']
                : false;
            unset($validated['allow_private_messages']);
        }
        if (array_key_exists('private_message_mode', $validated)) {
            $policy['private_message_mode'] = $global['allow_private_messages']
                ? (string) $validated['private_message_mode']
                : (string) $global['private_message_mode'];
            unset($validated['private_message_mode']);
        }
        $actionPolicy = is_array($policy['action_executor'] ?? null) ? $policy['action_executor'] : [];
        $actionMap = [
            'action_propose_before_execute' => 'propose_before_execute',
            'action_allow_create_post' => 'allow_create_post',
            'action_allow_create_poll' => 'allow_create_poll',
            'action_allow_create_comment' => 'allow_create_comment',
            'action_allow_react_message' => 'allow_react_message',
            'action_allow_react_post' => 'allow_react_post',
            'action_allow_react_comment' => 'allow_react_comment',
        ];
        foreach ($actionMap as $inputKey => $policyKey) {
            if (array_key_exists($inputKey, $validated)) {
                $globalKey = match ($policyKey) {
                    'propose_before_execute' => 'action_propose_before_execute',
                    'allow_create_post' => 'action_allow_create_post',
                    'allow_create_poll' => 'action_allow_create_poll',
                    'allow_create_comment' => 'action_allow_create_comment',
                    'allow_react_message' => 'action_allow_react_message',
                    'allow_react_post' => 'action_allow_react_post',
                    'allow_react_comment' => 'action_allow_react_comment',
                    default => null,
                };
                $allowedByGlobal = $global['action_executor_enabled'] && ($globalKey ? (bool) ($global[$globalKey] ?? true) : true);
                $actionPolicy[$policyKey] = $allowedByGlobal ? (bool) $validated[$inputKey] : false;
                unset($validated[$inputKey]);
            }
        }
        $actionPolicy['enabled'] = (bool) $global['action_executor_enabled'];
        $actionPolicy['max_actions_per_hour'] = (int) $global['action_max_per_hour'];
        $policy['action_executor'] = $actionPolicy;

        $config->fill($validated);
        $config->policy = $policy;
        $config->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تنظیمات نجم‌هدا برای این گروه ذخیره شد.',
        ]);
    }

    public function najmHodaActionItems(Request $request, Group $group)
    {
        if (!$this->hasGroupLeadershipAccess($group)) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما دسترسی لازم را ندارید.'
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|in:open,in_progress,blocked,done,cancelled',
            'q' => 'nullable|string|max:255',
        ]);

        $query = NajmHodaGroupActionItem::query()
            ->where('group_id', $group->id)
            ->with('assignedUser:id,first_name,last_name,email')
            ->latest('id');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['q'])) {
            $term = trim($validated['q']);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('details', 'like', "%{$term}%")
                    ->orWhere('assignee_name', 'like', "%{$term}%");
            });
        }

        $items = $query->take(100)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'details' => $item->details,
                'status' => $item->status,
                'priority' => $item->priority,
                'assignee_name' => $item->assignee_name,
                'assigned_user_id' => $item->assigned_user_id,
                'due_at' => optional($item->due_at)->format('Y-m-d\TH:i'),
                'due_human' => optional($item->due_at)->diffForHumans(),
                'updated_at_human' => optional($item->updated_at)->diffForHumans(),
            ];
        });

        $members = GroupUser::query()
            ->with('user:id,first_name,last_name,email')
            ->where('group_id', $group->id)
            ->where('status', 1)
            ->get()
            ->map(function ($member) {
                $fullName = trim(($member->user->first_name ?? '') . ' ' . ($member->user->last_name ?? ''));
                return [
                    'id' => $member->user_id,
                    'name' => $fullName !== '' ? $fullName : ($member->user->email ?? ('user#' . $member->user_id)),
                ];
            })->values();

        return response()->json([
            'status' => 'success',
            'items' => $items,
            'members' => $members,
        ]);
    }

    public function updateNajmHodaActionItem(Request $request, Group $group, NajmHodaGroupActionItem $actionItem)
    {
        if (!$this->hasGroupLeadershipAccess($group)) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما دسترسی لازم را ندارید.'
            ], 403);
        }

        if ((int) $actionItem->group_id !== (int) $group->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'مصوبه مربوط به این گروه نیست.'
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'nullable|in:open,in_progress,blocked,done,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'due_at' => 'nullable|date',
        ]);

        if (array_key_exists('assigned_user_id', $validated) && !empty($validated['assigned_user_id'])) {
            $isMember = GroupUser::where('group_id', $group->id)
                ->where('user_id', $validated['assigned_user_id'])
                ->where('status', 1)
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'کاربر انتخاب‌شده عضو فعال این گروه نیست.',
                ], 422);
            }
        }

        $actionItem->fill($validated);
        if (array_key_exists('assigned_user_id', $validated)) {
            if (!empty($validated['assigned_user_id'])) {
                $assignee = User::query()->select('id', 'first_name', 'last_name', 'email')->find($validated['assigned_user_id']);
                $fullName = trim(($assignee->first_name ?? '') . ' ' . ($assignee->last_name ?? ''));
                $actionItem->assignee_name = $fullName !== '' ? $fullName : ($assignee->email ?? null);
            } else {
                $actionItem->assignee_name = null;
            }
        }

        $actionItem->save();

        return response()->json([
            'status' => 'success',
            'message' => 'مصوبه با موفقیت بروزرسانی شد.',
            'item' => [
                'id' => $actionItem->id,
                'status' => $actionItem->status,
                'priority' => $actionItem->priority,
                'assignee_name' => $actionItem->assignee_name,
                'due_at' => optional($actionItem->due_at)->format('Y-m-d\TH:i'),
                'updated_at_human' => optional($actionItem->updated_at)->diffForHumans(),
            ],
        ]);
    }

}
