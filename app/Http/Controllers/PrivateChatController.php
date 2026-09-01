<?php

namespace App\Http\Controllers;

use App\Events\PrivateMessageCreated;
use App\Models\PrivateConversation;
use App\Models\PrivateMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivateChatController extends Controller
{
    public function index()
    {
        $currentUserId = (int) auth()->id();

        $conversations = PrivateConversation::whereHas('users', function ($query) use ($currentUserId) {
            $query->where('users.id', $currentUserId);
        })
        ->withCount([
            'messages as unread_count' => function ($query) use ($currentUserId) {
                $query->where('sender_id', '!=', $currentUserId)
                    ->whereNull('read_at');
            },
        ])
        ->with([
            'users:id,first_name,last_name,avatar',
            'messages' => function ($query) {
                $query->latest('id')->limit(1);
            }
        ])
        ->get()
        ->sortByDesc(function ($conversation) {
            return $conversation->messages->first()?->created_at ?? now();
        })
        ->values();

        return view('private-chats.index', compact('conversations'));
    }

    public function show(PrivateConversation $conversation)
    {
        $currentUserId = (int) auth()->id();
        $this->ensureParticipant($conversation, $currentUserId);

        $this->markIncomingMessagesRead($conversation, $currentUserId);

        $conversation->load('users:id,first_name,last_name,avatar');

        $recentMessages = $conversation->messages()
            ->with([
                'sender:id,first_name,last_name,avatar',
                'reactions.user:id,first_name,last_name,avatar',
            ])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->sortBy('id')
            ->values();

        $conversation->setRelation('messages', $recentMessages);

        $hasMoreMessages = $conversation->messages()
            ->where('id', '<', $recentMessages->first()?->id ?? 0)
            ->exists();

        return view('private-chats.show', [
            'conversation' => $conversation,
            'hasMoreMessages' => $hasMoreMessages,
        ]);
    }

    public function sendMessage(Request $request, PrivateConversation $conversation)
    {
        $currentUserId = (int) auth()->id();
        $isParticipant = $conversation->users()->where('users.id', $currentUserId)->exists();
        if (!$isParticipant) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $message = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $currentUserId,
            'message' => $data['message'],
        ]);

        $message->load([
            'sender:id,first_name,last_name,avatar',
            'reactions.user:id,first_name,last_name,avatar',
        ]);

        event(new PrivateMessageCreated($message, $conversation));

        $payload = [
            'success' => true,
            'message' => $this->formatMessage($message),
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        return redirect()->route('private-chats.show', $conversation->id)
            ->with('status', 'پیام شما ارسال شد.');
    }

    public function getMessages(Request $request, PrivateConversation $conversation): JsonResponse
    {
        $currentUserId = (int) auth()->id();
        $isParticipant = $conversation->users()->where('users.id', $currentUserId)->exists();
        if (!$isParticipant) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $afterId = $request->input('after_id');
        $beforeId = $request->input('before_id');
        $limit = min(100, max(1, (int) $request->input('limit', 50)));

        $query = $conversation->messages()
            ->with([
                'sender:id,first_name,last_name,avatar',
                'reactions.user:id,first_name,last_name,avatar',
            ]);

        if ($afterId) {
            $query->where('id', '>', $afterId)->orderBy('id', 'asc');
        } elseif ($beforeId) {
            $query->where('id', '<', $beforeId)->orderBy('id', 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        $messages = $query->limit($limit)->get();

        if ($beforeId) {
            $messages = $messages->sortBy('id')->values();
        }

        // Fetching new messages from an open conversation represents an active view.
        // Older-history pagination does not alter read state because those messages
        // have already passed through the active conversation surface.
        if (!$beforeId) {
            $incomingIds = $messages
                ->where('sender_id', '!=', $currentUserId)
                ->whereNull('read_at')
                ->pluck('id');

            if ($incomingIds->isNotEmpty()) {
                $readAt = now();
                PrivateMessage::query()
                    ->where('private_conversation_id', $conversation->id)
                    ->whereIn('id', $incomingIds)
                    ->where('sender_id', '!=', $currentUserId)
                    ->whereNull('read_at')
                    ->update(['read_at' => $readAt]);

                $messages->each(function (PrivateMessage $message) use ($incomingIds, $readAt) {
                    if ($incomingIds->contains($message->id)) {
                        $message->read_at = $readAt;
                    }
                });
            }
        }

        return response()->json([
            'messages' => $messages->map(fn($m) => $this->formatMessage($m)),
            'has_more' => $messages->count() >= $limit,
        ]);
    }

    public function getConversationInfo(PrivateConversation $conversation): JsonResponse
    {
        $currentUserId = (int) auth()->id();
        $isParticipant = $conversation->users()->where('users.id', $currentUserId)->exists();
        if (!$isParticipant) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $conversation->load('users:id,first_name,last_name,avatar');

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'users' => $conversation->users->map(fn($u) => [
                    'id' => $u->id,
                    'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                    'avatar' => $u->avatar ?? null,
                ]),
            ],
        ]);
    }

    private function ensureParticipant(PrivateConversation $conversation, int $currentUserId): void
    {
        $isParticipant = $conversation->users()->where('users.id', $currentUserId)->exists();

        if (!$isParticipant) {
            abort(403, 'Unauthorized');
        }
    }

    private function markIncomingMessagesRead(PrivateConversation $conversation, int $currentUserId): int
    {
        return $conversation->messages()
            ->where('sender_id', '!=', $currentUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function formatMessage($message): array
    {
        $sender = $message->sender;
        $reactionSummary = [];

        if ($message->relationLoaded('reactions')) {
            $reactionSummary = $message->reactions
                ->groupBy('reaction_type')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'users' => $group->map(fn($reaction) => $reaction->user?->fullName() ?? '')
                            ->filter()
                            ->unique()
                            ->values()
                            ->toArray(),
                    ];
                })
                ->toArray();
        }

        return [
            'id' => $message->id,
            'message' => $message->message,
            'sender' => [
                'id' => $sender->id,
                'name' => trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')),
                'avatar' => $sender->avatar ?? null,
            ],
            'created_at' => $message->created_at,
            'created_at_relative' => $message->created_at->diffForHumans(),
            'read_at' => $message->read_at,
            'is_read' => $message->read_at !== null,
            'reaction_summary' => $reactionSummary,
        ];
    }
}
