<?php

namespace App\Listeners;

use App\Events\MessageReported;
use App\Models\GroupUser;
use App\Services\NotificationService;

class SendMessageReportedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(MessageReported $event): void
    {
        $report = $event->report;
        $group = $event->group;
        $reporter = $event->reporter;

        // گیرندگان: مدیران (role=3) و بازرسان (role=2) گروه
        $recipientIds = GroupUser::where('group_id', $group->id)
            ->whereIn('role', [2, 3]) // بازرسان و مدیران
            ->where('status', 1) // فقط اعضای فعال
            ->pluck('user_id')
            ->all();

        if (empty($recipientIds)) {
            return;
        }

        $messagePreview = mb_substr($report->message->message ?? 'پیام حذف شده', 0, 100);
        $reporterName = $reporter->fullName();
        
        $title = 'گزارش پیام جدید در گروه ' . ($group->name ?? '');
        $message = "یک پیام در گروه {$group->name} توسط {$reporterName} گزارش شده است.\n";
        $message .= "دلیل: {$report->reason}\n";
        if ($report->description) {
            $message .= "توضیحات: " . mb_substr($report->description, 0, 100);
        }

        $url = route('groups.reports.index', $group->id);
        $context = [
            'group_id' => $group->id,
            'report_id' => $report->id,
            'message_id' => $report->message_id,
            'reporter_id' => $reporter->id,
        ];

        $this->notifications->notifyMany(
            $recipientIds,
            $title,
            $message,
            $url,
            'group.report.message',
            $context
        );
    }
}

