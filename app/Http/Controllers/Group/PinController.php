<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupFeedUpdated;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Message;
use App\Models\PinnedMessage;
use App\Models\Poll;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PinController extends Controller
{
    private const TYPES = [
        'message' => Message::class,
        'post' => Blog::class,
        'poll' => Poll::class,
        'announcement' => Announcement::class,
    ];

    public function index(Group $group): JsonResponse
    {
        $this->ensureMember($group);

        return response()->json(['status' => 'success', 'pins' => $this->items($group)]);
    }

    public function store(Request $request, Group $group): JsonResponse
    {
        $this->ensureModerator($group);
        [$type, $content] = $this->resolve($request, $group);

        $pin = PinnedMessage::firstOrCreate([
            'group_id' => $group->id,
            'content_type' => get_class($content),
            'content_id' => $content->getKey(),
        ], [
            'message_id' => $type === 'message' ? $content->getKey() : null,
            'announcement_id' => $type === 'announcement' ? $content->getKey() : null,
            'pinned_by' => auth()->id(),
        ]);

        $payload = $this->payload($group, $type, $content, true);
        if ($pin->wasRecentlyCreated) $this->broadcast($group, $payload);

        return response()->json(['status' => 'success', ...$payload]);
    }

    public function destroy(Request $request, Group $group): JsonResponse
    {
        $this->ensureModerator($group);
        [$type, $content] = $this->resolve($request, $group);

        PinnedMessage::where('group_id', $group->id)
            ->where('content_type', get_class($content))
            ->where('content_id', $content->getKey())
            ->delete();

        $payload = $this->payload($group, $type, $content, false);
        $this->broadcast($group, $payload);

        return response()->json(['status' => 'success', ...$payload]);
    }

    private function resolve(Request $request, Group $group): array
    {
        $validated = $request->validate([
            'content_type' => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'content_id' => 'required|integer|min:1',
        ]);
        $type = $validated['content_type'];
        $content = self::TYPES[$type]::findOrFail($validated['content_id']);

        if ($type === 'announcement') {
            abort_unless((string) $content->group_level === (string) $group->location_level, 404);
        } else {
            abort_unless((int) $content->group_id === (int) $group->id, 404);
        }

        return [$type, $content];
    }

    private function ensureMember(Group $group): void
    {
        abort_unless(GroupUser::where('group_id', $group->id)->where('user_id', auth()->id())->where('status', 1)->exists(), 403);
    }

    private function ensureModerator(Group $group): void
    {
        abort_unless(GroupUser::where('group_id', $group->id)->where('user_id', auth()->id())->where('status', 1)->whereIn('role', [2, 3])->exists(), 403);
    }

    private function payload(Group $group, string $type, Model $content, bool $pinned): array
    {
        return [
            'action' => 'pin_updated',
            'pinned' => $pinned,
            'pin' => $this->serializeContent($type, $content),
            'pinned_count' => PinnedMessage::where('group_id', $group->id)->count(),
        ];
    }

    private function items(Group $group): array
    {
        return PinnedMessage::with(['content', 'pinnedBy'])
            ->where('group_id', $group->id)->latest()->get()
            ->filter(fn ($pin) => $pin->content)
            ->map(function ($pin) {
                $type = array_search($pin->content_type, self::TYPES, true);
                if ($type === false) return null;

                return $this->serializeContent($type, $pin->content) + [
                    'pinned_at' => optional($pin->created_at)->toIso8601String(),
                    'pinned_by' => trim(($pin->pinnedBy->first_name ?? '') . ' ' . ($pin->pinnedBy->last_name ?? '')),
                ];
            })->filter()->values()->all();
    }

    private function serializeContent(string $type, Model $content): array
    {
        $isElection = $type === 'poll' && (int) ($content->main_type ?? 1) === 0;
        $label = match (true) {
            $type === 'announcement' => 'اطلاعیه',
            $type === 'post' => 'پست',
            $isElection => 'انتخابات',
            $type === 'poll' => 'نظرسنجی',
            !empty($content->voice_message) => 'پیام صوتی',
            default => 'پیام متنی',
        };
        $raw = match ($type) {
            'announcement' => trim(($content->title ? $content->title . ' — ' : '') . strip_tags((string) $content->content)),
            'post' => trim(($content->title ? $content->title . ' — ' : '') . strip_tags((string) $content->content)),
            'poll' => (string) $content->question,
            default => !empty($content->voice_message) ? ((string) $content->message ?: 'فایل صوتی') : (string) $content->message,
        };

        return [
            'key' => $type . ':' . $content->getKey(),
            'content_type' => $type,
            'content_id' => (int) $content->getKey(),
            'label' => $label,
            'preview' => Str::limit(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($raw))), 150),
            'anchor' => match ($type) {
                'announcement' => 'announcement-',
                'post' => 'blog-',
                'poll' => 'poll-',
                default => 'msg-',
            } . $content->getKey(),
            'image_url' => $type === 'announcement' && $content->image ? asset($content->image) : null,
            'created_at' => optional($content->created_at)->toIso8601String(),
        ];
    }

    private function broadcast(Group $group, array $payload): void
    {
        try {
            app(\App\Services\GroupChat\GroupEventPublisher::class)->publish(
                new GroupFeedUpdated((int) $group->id, 'pin_updated', $payload, (int) auth()->id())
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
