<?php

namespace App\Listeners;

use App\Events\MessageCreated;
use App\Models\GroupUser;
use App\Services\NotificationService;
use Illuminate\Support\Str;
use App\Models\Message as MessageModel;
use App\Models\Blog;
use App\Models\Poll;

class SendGroupMessageNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(MessageCreated $event): void
    {
        $group = $event->group;
        $message = $event->message;
        $sender = $event->sender;

        // گیرندگان: اعضای فعال گروه (status=1) به جز فرستنده
        $recipientIds = GroupUser::where('group_id', $group->id)
            ->where('status', 1)
            ->where('user_id', '!=', $sender->id)
            ->pluck('user_id')
            ->all();

        if (empty($recipientIds)) {
            return;
        }

        $title = 'پیام جدید در گروه ' . ($group->name ?? '');
        $preview = trim((string) ($message->message ?? '')); 
        if ($preview === '') {
            $preview = 'یک پیام جدید ارسال شد';
        }
        $preview = mb_substr($preview, 0, 120);

        $url = route('groups.chat', $group->id);
        $context = [
            'group_id' => $group->id,
            'message_id' => $message->id,
            'sender_id' => $sender->id,
        ];

        $this->notifications->notifyMany(
            $recipientIds,
            $title,
            $preview,
            $url,
            'chat.message',
            $context
        );

        // اگر ریپلای است، به صاحب آیتم والد هم اعلان بده
        if (!empty($message->parent_id)) {
            $parentId = (string) $message->parent_id;
            $parentUserId = null;
            $parentTitle = 'پاسخ جدید به مورد شما';

            if (Str::startsWith($parentId, 'poll-')) {
                $id = (int) Str::after($parentId, 'poll-');
                if ($poll = Poll::with('user')->find($id)) {
                    $parentUserId = $poll->user?->id;
                    $parentTitle = 'پاسخ جدید به نظرسنجی شما';
                }
            } elseif (Str::startsWith($parentId, 'post-')) {
                $id = (int) Str::after($parentId, 'post-');
                if ($post = Blog::with('user')->find($id)) {
                    $parentUserId = $post->user?->id;
                    $parentTitle = 'پاسخ جدید به پست شما';
                }
            } else {
                if ($parent = MessageModel::with('user')->find($parentId)) {
                    $parentUserId = $parent->user?->id;
                    $parentTitle = 'پاسخ جدید به پیام شما';
                }
            }

            if ($parentUserId && $parentUserId !== $sender->id) {
                $this->notifications->notifyUser(
                    $parentUserId,
                    $parentTitle,
                    $preview,
                    $url,
                    'chat.reply',
                    $context
                );
            }
        }
    }
}
