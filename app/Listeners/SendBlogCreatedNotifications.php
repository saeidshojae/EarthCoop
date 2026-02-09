<?php

namespace App\Listeners;

use App\Events\BlogCreated;
use App\Models\GroupUser;
use App\Services\NotificationService;
use Illuminate\Support\Str;

class SendBlogCreatedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(BlogCreated $event): void
    {
        $group = $event->group;
        $blog = $event->blog;
        $author = $event->author;

        // گیرندگان: اعضای فعال گروه (status=1) به جز نویسنده
        $recipientIds = GroupUser::where('group_id', $group->id)
            ->where('status', 1)
            ->where('user_id', '!=', $author->id)
            ->pluck('user_id')
            ->all();

        if (empty($recipientIds)) {
            return;
        }

        $title = 'پست جدید در گروه ' . ($group->name ?? '');
        $preview = trim((string) ($blog->title ?? ''));
        if ($preview === '') {
            $preview = 'یک پست جدید ارسال شد';
        }
        $preview = mb_substr($preview, 0, 120);

        $url = route('groups.chat', $group->id) . '#post-' . $blog->id;
        $context = [
            'group_id' => $group->id,
            'blog_id' => $blog->id,
            'author_id' => $author->id,
        ];

        $this->notifications->notifyMany(
            $recipientIds,
            $title,
            $preview,
            $url,
            'group.post',
            $context
        );
    }
}

