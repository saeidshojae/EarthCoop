<?php

namespace App\Http\Controllers;

use App\Models\ChatRequest;
use App\Models\PrivateConversation;
use App\Models\User;
use App\Notifications\ChatRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ChatRequestController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $tab = strtolower((string) $request->query('tab', 'pending'));
        if (!in_array($tab, ['pending', 'accepted', 'rejected'], true)) {
            $tab = 'pending';
        }

        $receivedQuery = ChatRequest::query()
            ->where('receiver_id', $currentUser->id)
            ->with(['sender'])
            ->latest();

        $sentQuery = ChatRequest::query()
            ->where('sender_id', $currentUser->id)
            ->with(['receiver'])
            ->latest();

        $counts = [
            'pending' => (clone $receivedQuery)->where('status', 'pending')->count(),
            'accepted' => (clone $receivedQuery)->where('status', 'accepted')->count(),
            'rejected' => (clone $receivedQuery)->where('status', 'rejected')->count(),
        ];

        $received = (clone $receivedQuery)->where('status', $tab)->get();
        $sent = (clone $sentQuery)->where('status', $tab)->get();

        return view('chat-requests.index', compact('tab', 'counts', 'received', 'sent'));
    }

    public function send(Request $request, User $user)
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        $input = $request->validate([
            'description' => 'required|string|max:5000',
        ]);

        $currentUser = auth()->user();

        if ((int) $currentUser->id === (int) $user->id) {
            return back()->with('error', 'Invalid request');
        }

        $existingRequest = ChatRequest::where(function ($query) use ($user, $currentUser) {
            $query->where('sender_id', $currentUser->id)->where('receiver_id', $user->id);
        })->orWhere(function ($query) use ($user, $currentUser) {
            $query->where('sender_id', $user->id)->where('receiver_id', $currentUser->id);
        })->latest('id')->first();

        if ($existingRequest) {
            if ($existingRequest->status === 'accepted' && $existingRequest->private_conversation_id) {
                return redirect()->route('private-chats.show', $existingRequest->private_conversation_id);
            }

                if ($existingRequest->status === 'accepted' && ! $existingRequest->private_conversation_id) {
                $conversation = $this->ensurePrivateConversationForRequest($existingRequest);

                return redirect()->route('private-chats.show', $conversation->id);
            }

            if ($existingRequest->status === 'rejected') {
                $existingRequest->update([
                    'sender_id' => $currentUser->id,
                    'receiver_id' => $user->id,
                    'message' => $input['description'],
                    'status' => 'pending',
                    'private_conversation_id' => null,
                ]);

                return back()->with('success', 'Chat request sent again');
            }

            return back()->with('error', 'Chat request already exists');
        }

        $chatRequest = ChatRequest::create([
            'sender_id' => $currentUser->id,
            'receiver_id' => $user->id,
            'message' => $input['description'],
            'status' => 'pending',
        ]);

        // Send notification to receiver
        Notification::send($user, new ChatRequestNotification(
            $chatRequest->id,
            $currentUser->fullName(),
            $input['description']
        ));

        return back()->with('success', 'Chat request sent');
    }

    public function accept(ChatRequest $chatRequest)
    {
        $currentUser = auth()->user();

        if ((int) $chatRequest->receiver_id !== (int) $currentUser->id) {
            return back()->with('error', 'Unauthorized');
        }

        if ($chatRequest->status !== 'pending') {
            return back()->with('error', 'Unauthorized');
        }

        $conversation = DB::transaction(function () use ($chatRequest, $currentUser) {
            $acceptedWithConversation = ChatRequest::where(function ($query) use ($chatRequest, $currentUser) {
                $query->where('sender_id', $currentUser->id)->where('receiver_id', $chatRequest->sender_id);
            })->orWhere(function ($query) use ($chatRequest, $currentUser) {
                $query->where('sender_id', $chatRequest->sender_id)->where('receiver_id', $currentUser->id);
            })->where('status', 'accepted')
                ->whereNotNull('private_conversation_id')
                ->latest('id')
                ->first();

            if ($acceptedWithConversation && $acceptedWithConversation->private_conversation_id) {
                $chatRequest->update([
                    'status' => 'accepted',
                    'private_conversation_id' => $acceptedWithConversation->private_conversation_id,
                ]);

                return PrivateConversation::find($acceptedWithConversation->private_conversation_id);
            }

            $chatRequest->update(['status' => 'accepted']);

            $conversation = PrivateConversation::create(['status' => 'active']);
            $conversation->users()->syncWithoutDetaching([$currentUser->id, $chatRequest->sender_id]);

            $chatRequest->update([
                'private_conversation_id' => $conversation->id,
            ]);

            return $conversation;
        });

        if (!$conversation) {
            return back()->with('error', 'Unauthorized');
        }

        return redirect()->route('private-chats.show', $conversation->id);
    }

    public function reject(ChatRequest $chatRequest)
    {
        $currentUser = auth()->user();

        if ((int) $chatRequest->receiver_id !== (int) $currentUser->id) {
            return back()->with('error', 'Unauthorized');
        }

        if ($chatRequest->status !== 'pending') {
            return back()->with('error', 'Unauthorized');
        }

        $chatRequest->update(['status' => 'rejected']);
        return back()->with('success', 'Chat request rejected');
    }

    public function pending()
    {
        $currentUser = request()->user();
        $pendingRequests = ChatRequest::where('receiver_id', $currentUser->id)
            ->where('status', 'pending')
            ->with('sender')
            ->get();

        return response()->json($pendingRequests);
    }

    /**
     * API: Get pending chat request count for badge display
     */
    public function pendingCount()
    {
        $currentUser = request()->user();
        $count = ChatRequest::where('receiver_id', $currentUser->id)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'pending_count' => $count,
        ]);
    }

    private function ensurePrivateConversationForRequest(ChatRequest $chatRequest): PrivateConversation
    {
        if ($chatRequest->private_conversation_id) {
            return $chatRequest->privateConversation()->firstOrFail();
        }

        $senderId = (int) $chatRequest->sender_id;
        $receiverId = (int) $chatRequest->receiver_id;

        $existingConversation = PrivateConversation::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', $senderId))
            ->whereHas('users', fn ($query) => $query->where('users.id', $receiverId))
            ->first();

        if (! $existingConversation) {
            $existingConversation = PrivateConversation::create(['status' => 'active']);
            $existingConversation->users()->syncWithoutDetaching([$senderId, $receiverId]);
        }

        $chatRequest->update([
            'private_conversation_id' => $existingConversation->id,
        ]);

        return $existingConversation;
    }
}
