<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Message;
use App\Models\Poll;
use App\Models\GroupUser;
use App\Services\GroupChat\GroupFeedService;
use Illuminate\Console\Command;

class BackfillGroupFeed extends Command
{
    protected $signature = 'group-chat:backfill-feed {--group=} {--dry-run} {--initialize-cursors}';
    protected $description = 'Backfill ordered group feed items from legacy group chat content.';

    public function handle(GroupFeedService $feed): int
    {
        config(['group-chat.features.feed_sequence_v1' => true]);
        if (! $feed->available()) {
            $this->error('Feed schema or feature flag is not available.');
            return self::FAILURE;
        }

        $query = Group::query()->orderBy('id');
        if ($this->option('group')) {
            $query->whereKey((int) $this->option('group'));
        }

        $created = 0;
        $query->each(function (Group $group) use ($feed, &$created): void {
            $items = collect();
            Message::where('group_id', $group->id)->get()->each(function (Message $message) use ($items): void {
                $items->push([
                    'type' => $message->voice_message ? 'voice' : ($message->file_path ? 'file' : 'message'),
                    'id' => $message->id, 'actor' => $message->user_id, 'at' => $message->created_at,
                ]);
            });
            Blog::where('group_id', $group->id)->get()->each(fn (Blog $post) => $items->push([
                'type' => 'post', 'id' => $post->id, 'actor' => $post->user_id, 'at' => $post->created_at,
            ]));
            Poll::where('group_id', $group->id)->get()->each(fn (Poll $poll) => $items->push([
                'type' => 'poll', 'id' => $poll->id, 'actor' => $poll->created_by, 'at' => $poll->created_at,
            ]));
            Comment::whereHas('blog', fn ($q) => $q->where('group_id', $group->id))->get()->each(fn (Comment $comment) => $items->push([
                'type' => 'comment', 'id' => $comment->id, 'actor' => $comment->user_id, 'at' => $comment->created_at,
            ]));

            foreach ($items->sortBy(fn ($item) => sprintf('%s:%020d', $item['at'], $item['id'])) as $item) {
                if (! $this->option('dry-run') && $feed->record((int) $group->id, $item['type'], (int) $item['id'], (int) $item['actor'], $item['at'])) {
                    $created++;
                }
            }
            if (! $this->option('dry-run') && $this->option('initialize-cursors')) {
                GroupUser::where('group_id', $group->id)->whereNotNull('last_read_message_id')->each(function (GroupUser $membership): void {
                    $sequence = (int) \App\Models\GroupFeedItem::whereIn('type', ['message', 'file', 'voice'])
                        ->where('content_id', $membership->last_read_message_id)->value('sequence');
                    if ($sequence > 0) {
                        $membership->update(['last_read_feed_sequence' => $sequence]);
                    }
                });
            }
            $this->line("group={$group->id} candidates={$items->count()}");
        });

        $this->info($this->option('dry-run') ? 'Dry run completed.' : "Backfill completed; processed={$created}.");
        return self::SUCCESS;
    }
}
