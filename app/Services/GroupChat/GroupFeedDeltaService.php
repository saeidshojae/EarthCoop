<?php

namespace App\Services\GroupChat;

use App\Models\Blog;
use App\Models\Comment;
use App\Models\Group;
use App\Models\GroupFeedItem;
use App\Models\Message;
use App\Models\Poll;
use Illuminate\Support\Collection;

final class GroupFeedDeltaService
{
    public function __construct(private readonly GroupFeedService $feed)
    {
    }

    public function forGroup(Group $group, int $after = 0, int $limit = 100): array
    {
        abort_unless(config('group-chat.features.delta_sync_v1', false) && $this->feed->available(), 409, 'Delta sync is not enabled.');

        $after = max(0, $after);
        $limit = min(max(1, $limit), 200);
        $rows = GroupFeedItem::query()
            ->where('group_id', $group->id)
            ->where('sequence', '>', $after)
            ->orderBy('sequence')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $content = $this->content($rows);

        return [
            'events' => $rows->map(fn (GroupFeedItem $item): array => [
                'version' => 1,
                'event_id' => 'feed:'.$item->id.':v'.$item->version,
                'group_id' => (int) $item->group_id,
                'sequence' => (int) $item->sequence,
                'type' => 'feed.'.$item->type.'.snapshot',
                'actor_id' => $item->actor_id ? (int) $item->actor_id : null,
                'occurred_at' => $item->occurred_at->toIso8601String(),
                'payload' => $content[$item->type][$item->content_id] ?? [
                    'content_type' => $item->type,
                    'content_id' => (int) $item->content_id,
                    'missing' => true,
                ],
            ])->all(),
            'after_sequence' => $after,
            'latest_sequence' => (int) ($rows->last()?->sequence ?? $after),
            'has_more' => $hasMore,
        ];
    }

    private function content(Collection $items): array
    {
        $byType = $items->groupBy('type');
        $result = [];
        $messageIds = collect(['message', 'file', 'voice'])
            ->flatMap(fn (string $type) => $byType->get($type, collect())->pluck('content_id'));

        Message::with('user:id,first_name,last_name')->whereIn('id', $messageIds)->get()
            ->each(function (Message $message) use (&$result): void {
                $type = $message->voice_message ? 'voice' : ($message->file_path ? 'file' : 'message');
                $result[$type][$message->id] = [
                    'content_type' => $type,
                    'content_id' => (int) $message->id,
                    'message' => $message->message,
                    'user_id' => (int) $message->user_id,
                    'sender' => trim(($message->user->first_name ?? '').' '.($message->user->last_name ?? '')),
                    'created_at' => $message->created_at->format('H:i'),
                    'parent_id' => $message->parent_id,
                    'state' => $message->lifecycle_state ?? 'sent',
                ];
            });

        Blog::whereIn('id', $byType->get('post', collect())->pluck('content_id'))->get()
            ->each(function (Blog $post) use (&$result): void {
                $result['post'][$post->id] = [
                    'content_type' => 'post',
                    'content_id' => (int) $post->id,
                    'title' => $post->title,
                    'content' => $post->content,
                ];
            });

        Poll::whereIn('id', $byType->get('poll', collect())->pluck('content_id'))
            ->with('options:id,poll_id,text')->get()
            ->each(function (Poll $poll) use (&$result): void {
                $result['poll'][$poll->id] = [
                    'content_type' => 'poll',
                    'content_id' => (int) $poll->id,
                    'question' => $poll->question,
                    'options' => $poll->options,
                ];
            });

        Comment::whereIn('id', $byType->get('comment', collect())->pluck('content_id'))
            ->with(['blog' => fn ($query) => $query->withCount('comments')])
            ->get()->each(function (Comment $comment) use (&$result): void {
                $result['comment'][$comment->id] = [
                    'content_type' => 'comment',
                    'content_id' => (int) $comment->id,
                    'blog_id' => (int) $comment->blog_id,
                    'message' => $comment->message,
                    'comments_count' => (int) ($comment->blog->comments_count ?? 0),
                ];
            });

        return $result;
    }
}
