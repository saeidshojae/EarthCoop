<?php

namespace App\Listeners;

use App\Events\ChatRequestToGroup;
use App\Models\GroupUser;
use App\Services\NotificationService;

class SendChatRequestToGroupNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(ChatRequestToGroup $event): void
    {
        $chatRequest = $event->chatRequest;
        $group = $event->group;
        $sender = $event->sender;

        // گیرندگان: مدیران گروه (role=3)
        $recipientIds = GroupUser::where('group_id', $group->id)
            ->where('role', 3) // فقط مدیران
            ->where('status', 1) // فقط اعضای فعال
            ->pluck('user_id')
            ->all();

        if (empty($recipientIds)) {
            return;
        }

        $senderName = $sender->fullName();
        $messagePreview = mb_substr($chatRequest->message ?? '', 0, 100);
        
        $title = 'درخواست چت جدید برای گروه ' . ($group->name ?? '');
        $message = "{$senderName} درخواست چت برای گروه {$group->name} ارسال کرده است.\n";
        if ($messagePreview) {
            $message .= "پیام: {$messagePreview}";
        }

        // لینک به صفحه مدیریت گروه یا چت‌ریکوئست‌ها
        $url = route('groups.chat', $group->id);
        $context = [
            'group_id' => $group->id,
            'chat_request_id' => $chatRequest->id,
            'sender_id' => $sender->id,
        ];

        $this->notifications->notifyMany(
            $recipientIds,
            $title,
            $message,
            $url,
            'group.chat.request',
            $context
        );
    }
}

