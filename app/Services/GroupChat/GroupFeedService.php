<?php

namespace App\Services\GroupChat;

use App\Models\GroupFeedItem;
use App\Models\GroupUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GroupFeedService
{
    public function available(): bool
    {
        return (bool) config('group-chat.features.feed_sequence_v1', true)
            && Schema::hasTable('group_feed_items')
            && Schema::hasTable('group_feed_sequences')
            && Schema::hasColumn('group_user', 'last_read_feed_sequence');
    }

    public function record(int $groupId, string $type, int $contentId, ?int $actorId, $occurredAt = null): ?GroupFeedItem
    {
        if (! $this->available()) {
            return null;
        }

        return DB::transaction(function () use ($groupId, $type, $contentId, $actorId, $occurredAt) {
            $existing = GroupFeedItem::where('type', $type)->where('content_id', $contentId)->first();
            if ($existing) {
                return $existing;
            }

            DB::table('group_feed_sequences')->insertOrIgnore([
                'group_id' => $groupId,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $counter = DB::table('group_feed_sequences')->where('group_id', $groupId)->lockForUpdate()->first();
            $sequence = (int) $counter->last_sequence + 1;
            DB::table('group_feed_sequences')->where('group_id', $groupId)->update([
                'last_sequence' => $sequence,
                'updated_at' => now(),
            ]);

            $item = GroupFeedItem::create([
                'group_id' => $groupId,
                'sequence' => $sequence,
                'type' => $type,
                'content_id' => $contentId,
                'actor_id' => $actorId,
                'occurred_at' => $occurredAt ? Carbon::parse($occurredAt) : now(),
            ]);
            if (config('group-chat.features.transactional_outbox_v1', false) && Schema::hasTable('group_chat_outbox')) {
                DB::table('group_chat_outbox')->insert([
                    'event_id' => (string) Str::uuid(),
                    'group_id' => $groupId,
                    'feed_item_id' => $item->id,
                    'sequence' => $sequence,
                    'type' => 'feed.' . $type . '.created',
                    'actor_id' => $actorId,
                    'payload' => json_encode(['content_type' => $type, 'content_id' => $contentId, 'version' => 1], JSON_UNESCAPED_UNICODE),
                    'status' => 'pending',
                    'attempts' => 0,
                    'available_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $item;
        }, 3);
    }

    public function unreadCounts(int $groupId, int $userId): array
    {
        $cursor = (int) GroupUser::where('group_id', $groupId)->where('user_id', $userId)->value('last_read_feed_sequence');
        $counts = GroupFeedItem::where('group_id', $groupId)
            ->where('sequence', '>', $cursor)
            ->where(fn ($query) => $query->whereNull('actor_id')->orWhere('actor_id', '!=', $userId))
            ->selectRaw('type, COUNT(*) as aggregate')->groupBy('type')->pluck('aggregate', 'type');

        $unreadBase = GroupFeedItem::where('group_feed_items.group_id', $groupId)
            ->where('group_feed_items.sequence', '>', $cursor)
            ->where(fn ($query) => $query->whereNull('group_feed_items.actor_id')->orWhere('group_feed_items.actor_id', '!=', $userId));
        $firstUnread = (int) (clone $unreadBase)->min('group_feed_items.sequence');
        $messageTypes = ['message', 'file', 'voice'];
        $mentions = (clone $unreadBase)->whereIn('group_feed_items.type', $messageTypes)
            ->join('messages', 'messages.id', '=', 'group_feed_items.content_id')
            ->where(function ($query) use ($userId) {
                $query->where('messages.message', 'like', '%data-mention-user-id="' . $userId . '"%')
                    ->orWhere('messages.message', 'like', '%@[' . $userId . ']%');
            })->count();
        $replies = (clone $unreadBase)->whereIn('group_feed_items.type', $messageTypes)
            ->join('messages', 'messages.id', '=', 'group_feed_items.content_id')
            ->join('messages as parent_messages', 'parent_messages.id', '=', 'messages.parent_id')
            ->where('parent_messages.user_id', $userId)->count();

        return [
            'total' => (int) $counts->sum(),
            'messages' => (int) ($counts['message'] ?? 0) + (int) ($counts['file'] ?? 0) + (int) ($counts['voice'] ?? 0),
            'files' => (int) ($counts['file'] ?? 0),
            'voices' => (int) ($counts['voice'] ?? 0),
            'posts' => (int) ($counts['post'] ?? 0),
            'polls' => (int) ($counts['poll'] ?? 0),
            'comments' => (int) ($counts['comment'] ?? 0),
            'mentions' => (int) $mentions,
            'replies' => (int) $replies,
            'first_unread_sequence' => $firstUnread ?: null,
            'cursor' => $cursor,
        ];
    }

    public function recordMutation(string $contentType, int $contentId, string $eventType, ?int $actorId, array $payload = []): void
    {
        if (! $this->available() || ! config('group-chat.features.transactional_outbox_v1', false) || ! Schema::hasTable('group_chat_outbox')) {
            return;
        }

        DB::transaction(function () use ($contentType, $contentId, $eventType, $actorId, $payload): void {
            $item = GroupFeedItem::where('type', $contentType)->where('content_id', $contentId)->lockForUpdate()->first();
            if (! $item) return;
            $item->increment('version');
            $version = (int) $item->fresh()->version;
            DB::table('group_chat_outbox')->insert([
                'event_id' => (string) Str::uuid(), 'group_id' => $item->group_id, 'feed_item_id' => $item->id,
                'sequence' => $item->sequence, 'type' => $eventType, 'actor_id' => $actorId,
                'payload' => json_encode(array_merge($payload, ['content_type' => $contentType, 'content_id' => $contentId, 'version' => $version]), JSON_UNESCAPED_UNICODE),
                'status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function markRead(int $groupId, int $userId, ?int $throughSequence = null): int
    {
        return DB::transaction(function () use ($groupId, $userId, $throughSequence): int {
            $membership = GroupUser::where('group_id', $groupId)->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $latest = (int) DB::table('group_feed_sequences')->where('group_id', $groupId)->value('last_sequence');
            $target = min($throughSequence ?? $latest, $latest);
            $target = max((int) $membership->last_read_feed_sequence, $target);
            $membership->update(['last_read_feed_sequence' => $target]);

            return $target;
        }, 3);
    }
}
