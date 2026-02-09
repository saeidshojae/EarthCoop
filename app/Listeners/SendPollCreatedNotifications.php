<?php

namespace App\Listeners;

use App\Events\PollCreated;
use App\Models\GroupUser;
use App\Services\NotificationService;

class SendPollCreatedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(PollCreated $event): void
    {
        $group = $event->group;
        $poll = $event->poll;
        $creator = $event->creator;

        // گیرندگان: اعضای فعال گروه (status=1) به جز سازنده
        $recipientIds = GroupUser::where('group_id', $group->id)
            ->where('status', 1)
            ->where('user_id', '!=', $creator->id)
            ->pluck('user_id')
            ->all();

        if (empty($recipientIds)) {
            return;
        }

        $title = 'نظرسنجی جدید در گروه ' . ($group->name ?? '');
        $preview = trim((string) ($poll->question ?? ''));
        if ($preview === '') {
            $preview = 'یک نظرسنجی جدید ایجاد شد';
        }
        $preview = mb_substr($preview, 0, 120);

        $url = route('groups.chat', $group->id) . '#poll-' . $poll->id;
        $context = [
            'group_id' => $group->id,
            'poll_id' => $poll->id,
            'creator_id' => $creator->id,
        ];

        $this->notifications->notifyMany(
            $recipientIds,
            $title,
            $preview,
            $url,
            'group.poll',
            $context
        );
    }
}

