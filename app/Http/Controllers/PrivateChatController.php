<?php

namespace App\Http\Controllers;

use App\Models\PrivateConversation;
use App\Models\PrivateMessage;
use Illuminate\Http\Request;

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

    public function sendMessage(Request $request, PrivateConversation $conversation)
    {
        $currentUserId = (int) auth()->id();
        $isParticipant = $conversation->users()->where('users.id', $currentUserId)->exists();
        if (!$isParticipant) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $currentUserId,
            'message' => $data['message'],
        ]);

        return redirect()->route('private-chats.show', $conversation->id);
    }
}
