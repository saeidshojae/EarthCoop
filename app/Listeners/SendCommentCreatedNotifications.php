<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Services\NotificationService;

class SendCommentCreatedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(CommentCreated $event): void
    {
        $blog = $event->blog;
        $comment = $event->comment;
        $author = $event->author;
        $group = $event->group;

        // اگر کامنت پاسخ به کامنت دیگری است، به صاحب کامنت والد اعلان بده
        if ($comment->parent_id) {
            $parentComment = $comment->parent;
            if ($parentComment && $parentComment->user_id !== $author->id) {
                $title = 'پاسخ جدید به کامنت شما';
                $preview = mb_substr(trim((string) ($comment->message ?? '')), 0, 120);
                if ($preview === '') {
                    $preview = 'یک پاسخ جدید دریافت کردید';
                }
                
                $url = route('groups.chat', $group->id) . '#comment-' . $comment->id;
                $context = [
                    'group_id' => $group->id,
                    'blog_id' => $blog->id,
                    'comment_id' => $comment->id,
                    'parent_comment_id' => $comment->parent_id,
                    'author_id' => $author->id,
                ];

                $this->notifications->notifyUser(
                    $parentComment->user_id,
                    $title,
                    $preview,
                    $url,
                    'group.comment.reply',
                    $context
                );
            }
        }

        // به نویسنده پست اعلان بده (اگر خودش کامنت نزده باشد)
        if ($blog->user_id !== $author->id) {
            $title = 'کامنت جدید روی پست شما';
            $preview = mb_substr(trim((string) ($comment->message ?? '')), 0, 120);
            if ($preview === '') {
                $preview = 'یک کامنت جدید دریافت کردید';
            }
            
            $url = route('groups.chat', $group->id) . '#comment-' . $comment->id;
            $context = [
                'group_id' => $group->id,
                'blog_id' => $blog->id,
                'comment_id' => $comment->id,
                'author_id' => $author->id,
            ];

            $this->notifications->notifyUser(
                $blog->user_id,
                $title,
                $preview,
                $url,
                'group.comment.new',
                $context
            );
        }
    }
}

