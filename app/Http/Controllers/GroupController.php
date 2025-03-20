<?php
namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function show(Group $group)
    {
        // بررسی عضویت کاربر در گروه
        if (!$group->users()->where('user_id', auth()->id())->exists()) {
            return redirect()->route('home')->with('error', 'شما عضو این گروه نیستید.');
        }

        // دریافت پیام‌ها با اطلاعات کاربر فرستنده
        $messages = $group->messages()
            ->with(['user' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'avatar')
                    ->selectRaw("CONCAT(first_name, ' ', last_name) as full_name");
            }])
            ->orderBy('created_at', 'asc')
            ->get();

        // دریافت اعضای گروه با استفاده از رابطه Eloquent
        $members = DB::table('users')
            ->join('group_user', 'users.id', '=', 'group_user.user_id')
            ->where('group_user.group_id', $group->id)
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.avatar',
                'group_user.role',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as full_name")
            )
            ->distinct()
            ->get();

        // لاگ کردن اطلاعات برای عیب‌یابی
        \Log::info('Group ID: ' . $group->id);
        \Log::info('Messages count: ' . $messages->count());
        \Log::info('Members count: ' . $members->count());
        \Log::info('Members:', $members->toArray());
        \Log::info('Current user ID: ' . auth()->id());

        // تنظیم مسیر پیش‌فرض برای عکس پروفایل
        $members = collect($members)->map(function($member) {
            if (!$member->avatar) {
                $member->avatar = 'images/default-profile.png';
            }
            return $member;
        });

        return view('groups.show', compact('group', 'messages', 'members'));
    }
}