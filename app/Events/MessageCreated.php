<?php

namespace App\Events;

use App\Models\Blog;
use App\Models\Group;
use App\Models\Message;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public Group $group,
        public User $sender
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . $this->group->id)];
    }

    public function broadcastAs(): string
    {
        return 'group.message.created';
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('group-chat.features.realtime_messages', false);
    }

    public function broadcastWith(): array
    {
        $payload = [
            'id' => $this->message->id,
            'user_id' => $this->message->user_id,
            'message' => $this->message->message,
            'created_at' => optional($this->message->created_at)->format('H:i'),
            'sender' => trim(($this->sender->first_name ?? '') . ' ' . ($this->sender->last_name ?? '')) ?: 'کاربر',
            'parent_id' => $this->message->parent_id,
            'file_path' => $this->message->file_path,
            'file_type' => $this->message->file_type,
            'file_name' => $this->message->file_name,
            'voice_message' => $this->normalizeVoicePath($this->message->voice_message),
            'voice_message_url' => $this->message->voice_message ? route('groups.messages.voice', ['message' => $this->message->id]) : null,
            'reactions' => [],
        ];

        if ($this->message->parent_id) {
            [$parentSender, $parentContent] = $this->resolveParentPreview((string) $this->message->parent_id);
            if ($parentSender !== null) {
                $payload['parent_sender'] = $parentSender;
            }
            if ($parentContent !== null) {
                $payload['parent_content'] = $parentContent;
            }
        }

        return [
            'group_id' => $this->group->id,
            'actor_id' => $this->sender->id,
            'message' => $payload,
        ];
    }

    private function normalizeVoicePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $path = ltrim($path, '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
        if (Str::startsWith($path, 'storage/')) {
            return '/' . $encoded;
        }
        return '/storage/' . $encoded;
    }

    private function resolveParentPreview(string $parentId): array
    {
        if (Str::startsWith($parentId, 'poll-')) {
            $poll = Poll::with('user')->find((int) Str::after($parentId, 'poll-'));
            if ($poll) {
                return [
                    trim(($poll->user->first_name ?? '') . ' ' . ($poll->user->last_name ?? '')) ?: 'کاربر',
                    $poll->title ?? $poll->question ?? '',
                ];
            }
            return [null, null];
        }

        if (Str::startsWith($parentId, 'post-')) {
            $post = Blog::with('user')->find((int) Str::after($parentId, 'post-'));
            if ($post) {
                return [
                    trim(($post->user->first_name ?? '') . ' ' . ($post->user->last_name ?? '')) ?: 'کاربر',
                    $post->title ?? '',
                ];
            }
            return [null, null];
        }

        $parentMessage = Message::with('user')->find((int) $parentId);
        if (!$parentMessage) {
            return [null, null];
        }

        return [
            trim(($parentMessage->user->first_name ?? '') . ' ' . ($parentMessage->user->last_name ?? '')) ?: 'کاربر',
            $parentMessage->message ?? '',
        ];
    }
}
