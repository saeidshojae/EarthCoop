<?php

namespace App\Http\Controllers;

use App\Models\MessageReaction;
use App\Models\PrivateMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageReactionController extends Controller
{
    /**
     * Add or toggle a reaction on a message
     */
    public function store(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:private_messages,id',
            'reaction_type' => 'required|string|max:10|in:👍,❤️,😂,😮,😢,🔥,👎',
        ]);

        $user = Auth::user();
        $messageId = $request->message_id;
        $reactionType = $request->reaction_type;

        // Check if user is in the conversation
        $message = PrivateMessage::with('conversation.users')
            ->findOrFail($messageId);

        $inConversation = $message->conversation->users->contains($user->id);

        if (!$inConversation) {
            return response()->json([
                'success' => false,
                'error' => 'دسترسی ندارید',
            ], 403);
        }

        // Upsert reaction
        $reaction = MessageReaction::updateOrCreate(
            [
                'message_id' => $messageId,
                'message_type' => PrivateMessage::class,
                'user_id' => $user->id,
            ],
            ['reaction_type' => $reactionType]
        );

        // Get updated reaction summary
        $reactions = $message->reactions
            ->groupBy('reaction_type')
            ->map(fn($group) => [
                'count' => $group->count(),
                'users' => $group->pluck('user.full_name')->unique('full_name')->values()->toArray(),
            ])
            ->filter(fn($data) => $data['count'] > 0)
            ->toArray();

        return response()->json([
            'success' => true,
            'reaction_id' => $reaction->id,
            'reactions' => $reactions,
        ]);
    }

    /**
     * Remove a reaction
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:private_messages,id',
        ]);

        $user = Auth::user();
        $messageId = $request->message_id;

        $deleted = MessageReaction::where([
            'message_id' => $messageId,
            'message_type' => PrivateMessage::class,
            'user_id' => $user->id,
        ])->delete();

        return response()->json([
            'success' => $deleted,
        ]);
    }

    /**
     * Get reactions for a message (for showing who reacted)
     */
    public function index(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:private_messages,id',
        ]);

        $message = PrivateMessage::with(['reactions.user:id,full_name,avatar'])
            ->findOrFail($request->message_id);

        $reactions = $message->reactions
            ->groupBy('reaction_type')
            ->map(fn($group) => [
                'type' => $group->first()->reaction_type,
                'count' => $group->count(),
                'users' => $group->pluck('user')->map(fn($u) => [
                    'name' => $u->full_name,
                    'avatar' => $u->avatar,
                ])->toArray(),
            ])
            ->values()
            ->toArray();

        return response()->json([
            'reactions' => $reactions,
        ]);
    }
}