<?php

namespace App\Listeners;

use App\Events\UserMentioned;
use App\Services\NotificationService;

class SendMentionNotification
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(UserMentioned $event): void
    {
        $mentionedUser = $event->mentionedUser;
        $message = $event->message;
        $group = $event->group;
        $mentioner = $event->mentioner;

        // بررسی اینکه کاربر mention شده عضو گروه است
        $isMember = $group->users()->whereKey($mentionedUser->id)->exists();
        if (!$isMember) {
            return;
        }

        $title = $mentioner->fullName() . ' شما را در گروه ' . ($group->name ?? '') . ' mention کرد';
        $preview = trim((string) ($message->message ?? ''));
        if ($preview === '') {
            $preview = 'یک پیام جدید';
        }
        $preview = mb_substr($preview, 0, 120);

        $url = route('groups.chat', $group->id) . '#msg-' . $message->id;
        $context = [
            'group_id' => $group->id,
            'message_id' => $message->id,
            'sender_id' => $mentioner->id,
            'mention' => true,
        ];

        $this->notifications->notifyUser(
            $mentionedUser->id,
            $title,
            $preview,
            $url,
            'chat.mention',
            $context
        );
    }
}

