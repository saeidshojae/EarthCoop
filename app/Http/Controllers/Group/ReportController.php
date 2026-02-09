<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\ReportedMessage;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * دریافت لیست گزارش‌های در انتظار بررسی مدیران گروه
     * فقط برای مدیران (role 3)
     */
    public function index(Group $group)
    {
        // بررسی اینکه کاربر فعلی مدیر است
        $groupUser = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->first();
        
        if (!$groupUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما عضو این گروه نیستید.'
            ], 403);
        }
        
        // تعیین نقش بر اساس location_level (مثل ChatController):
        // - سطح محله و پایین‌تر (neighborhood, street, alley) → فعال (role 1)
        // - سطح منطقه و بالاتر (region, village, rural, city و ...) → ناظر (role 0)
        // اگر role در pivot وجود داشت و معتبر بود (2, 3, 4, 5)، از همان استفاده می‌کنیم
        $pivotRole = (int)$groupUser->role;
        
        if (in_array($pivotRole, [2, 3, 4, 5], true)) {
            // نقش‌های خاص (بازرس، مدیر، مهمان، فعال۲) از pivot استفاده می‌شوند
            $yourRole = $pivotRole;
        } else {
            // در غیر این صورت، بر اساس location_level تعیین می‌کنیم
            $locationLevel = strtolower(trim((string)($group->location_level ?? '')));
            if (in_array($locationLevel, ['neighborhood', 'street', 'alley'], true)) {
                $yourRole = 1; // عضو فعال
            } else {
                $yourRole = 0; // ناظر
            }
        }
        
        // فقط مدیران (role 3) دسترسی دارند
        if ($yourRole !== 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما دسترسی لازم را ندارید.'
            ], 403);
        }

        // دریافت گزارش‌های در انتظار بررسی (pending و هنوز به ادمین ارجاع نشده)
        $reports = ReportedMessage::where('group_id', $group->id)
            ->where('status', 'pending')
            ->where('escalated_to_admin', false)
            ->with(['message.user', 'reporter'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'message_id' => $report->message_id,
                    'message_content' => $report->message->message ?? 'پیام حذف شده',
                    'message_author' => $report->message->user->fullName() ?? 'کاربر ناشناس',
                    'reporter_name' => $report->reporter->fullName() ?? 'کاربر ناشناس',
                    'reporter_email' => $report->reporter->email ?? '',
                    'reason' => $report->reason,
                    'description' => $report->description,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $report->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'reports' => $reports
        ]);
    }

    /**
     * بررسی گزارش توسط مدیر گروه
     * فقط برای مدیران (role 3)
     */
    public function review(Request $request, Group $group, ReportedMessage $report)
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

        // بررسی اینکه گزارش مربوط به این گروه است
        if ($report->group_id != $group->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'این گزارش مربوط به این گروه نیست.'
            ], 400);
        }

        $validated = $request->validate([
            'action' => 'required|in:resolve,dismiss,escalate',
            'manager_note' => 'nullable|string|max:1000'
        ]);

        $report->reviewed_by_manager = auth()->id();
        $report->reviewed_at = now();
        $report->manager_note = $validated['manager_note'] ?? null;

        switch ($validated['action']) {
            case 'resolve':
                // حذف پیام و حل گزارش
                if ($report->message) {
                    $report->message->removed_by = auth()->id();
                    $report->message->save();
                }
                // استفاده از status موجود یا ایجاد status جدید
                $report->status = 'resolved'; // یا 'resolved_by_group_manager' اگر migration status را تغییر داده باشد
                $message = 'گزارش با موفقیت حل شد و پیام حذف شد.';
                break;

            case 'dismiss':
                // رد گزارش
                $report->status = 'dismissed';
                $message = 'گزارش رد شد.';
                break;

            case 'escalate':
                // ارجاع به ادمین سایت
                // استفاده از status موجود یا ایجاد status جدید
                $report->status = 'reviewed'; // یا 'escalated_to_admin' اگر migration status را تغییر داده باشد
                $report->escalated_to_admin = true;
                $report->escalated_at = now();
                $message = 'گزارش به ادمین سایت ارجاع شد.';
                break;

            default:
                return response()->json([
                    'status' => 'error',
                    'message' => 'عملیات نامعتبر است.'
                ], 400);
        }

        $report->save();

        return response()->json([
            'status' => 'success',
            'message' => $message
        ]);
    }

    /**
     * مشاهده جزئیات یک گزارش
     * فقط برای مدیران (role 3)
     */
    public function show(Group $group, ReportedMessage $report)
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

        // بررسی اینکه گزارش مربوط به این گروه است
        if ($report->group_id != $group->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'این گزارش مربوط به این گروه نیست.'
            ], 400);
        }

        $report->load(['message.user', 'reporter', 'reviewedByManager']);

        return response()->json([
            'status' => 'success',
            'report' => [
                'id' => $report->id,
                'message_id' => $report->message_id,
                'message' => [
                    'id' => $report->message->id ?? null,
                    'content' => $report->message->message ?? 'پیام حذف شده',
                    'author' => $report->message->user->fullName() ?? 'کاربر ناشناس',
                    'created_at' => $report->message->created_at->format('Y-m-d H:i:s') ?? null,
                ],
                'reporter' => [
                    'id' => $report->reporter->id ?? null,
                    'name' => $report->reporter->fullName() ?? 'کاربر ناشناس',
                    'email' => $report->reporter->email ?? '',
                ],
                'reason' => $report->reason,
                'description' => $report->description,
                'status' => $report->status,
                'manager_note' => $report->manager_note,
                'reviewed_by_manager' => $report->reviewedByManager->fullName() ?? null,
                'reviewed_at' => $report->reviewed_at?->format('Y-m-d H:i:s'),
                'escalated_to_admin' => $report->escalated_to_admin,
                'escalated_at' => $report->escalated_at?->format('Y-m-d H:i:s'),
                'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $report->created_at->diffForHumans(),
            ]
        ]);
    }
}
