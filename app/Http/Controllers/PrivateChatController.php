<?php

namespace App\Http\Controllers;

use App\Models\PrivateConversation;
use App\Models\PrivateMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrivateChatController extends Controller
{
    public function show(PrivateConversation $conversation)
    {
        $currentUserId = (int) auth()->id();
        $isParticipant = $conversation->users()->where('users.id', $currentUserId)->exists();
        if (!$isParticipant) {
            abort(403, 'Unauthorized');
        }

        $conversation->load([
            'users:id,first_name,last_name,avatar',
            'messages' => function ($query) {
                $query->with('sender:id,first_name,last_name,avatar')->orderBy('id');
            },
        ]);

        return view('private-chats.show', [
            'conversation' => $conversation,
        ]);
    }

    public function sendMessage(Request $request, PrivateConversation $conversation): JsonResponse
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

        // Load sender info for the response
        $message->load('sender:id,first_name,last_name,avatar');

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message),
        ]);
    }

    public function getMessages(Request $request, PrivateConversation $conversation): JsonResponse
    {
        $currentUserId = (int) auth()->id();
        $isParticipant = $conversation->users()->where('users.id', $currentUserId)->exists();
        if (!$isParticipant) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $afterId = $request->input('after_id');
        
        $query = $conversation->messages()
            ->with('sender:id,first_name,last_name,avatar')
            ->orderBy('id', 'asc');

        if ($afterId) {
            $query->where('id', '>', $afterId);
        }

        $messages = $query->limit(50)->get();

        return response()->json([
            'messages' => $messages->map(fn($m) => $this->formatMessage($m)),
            'has_more' => $messages->count() >= 50,
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

    private function formatMessage($message): array
    {
        $sender = $message->sender;
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
        ];
    }
}
