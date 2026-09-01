<?php

namespace App\Http\Controllers;

use App\Models\ChatRequest;
use App\Models\PrivateConversation;
use App\Models\User;
use App\Models\GroupUser;
use App\Notifications\ChatRequestAcceptedNotification;
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
        $section = strtolower((string) $request->query('section', $request->query('tab', 'requests')));
        if (!in_array($section, ['requests', 'conversations'], true)) {
            $section = 'requests';
        }

        $box = strtolower((string) $request->query('box', 'received'));
        if (!in_array($box, ['received', 'sent'], true)) {
            $box = 'received';
        }

        $status = strtolower((string) $request->query('status', 'pending'));
        if (!in_array($status, ['all', 'pending', 'accepted', 'rejected'], true)) {
            $status = 'pending';
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

        $requestCounts = [
            'received' => [
                'all' => (clone $receivedQuery)->count(),
                'pending' => $counts['pending'],
                'accepted' => $counts['accepted'],
                'rejected' => $counts['rejected'],
            ],
            'sent' => [
                'all' => (clone $sentQuery)->count(),
                'pending' => (clone $sentQuery)->where('status', 'pending')->count(),
                'accepted' => (clone $sentQuery)->where('status', 'accepted')->count(),
                'rejected' => (clone $sentQuery)->where('status', 'rejected')->count(),
            ],
        ];

        $received = $status === 'all'
            ? (clone $receivedQuery)->get()
            : (clone $receivedQuery)->where('status', $status)->get();

        $sent = $status === 'all'
            ? (clone $sentQuery)->get()
            : (clone $sentQuery)->where('status', $status)->get();

        $conversations = PrivateConversation::whereHas('users', function ($query) use ($currentUser) {
                $query->where('users.id', $currentUser->id);
            })
            ->withCount([
                'messages as unread_count' => function ($query) use ($currentUser) {
                    $query->where('sender_id', '!=', $currentUser->id)
                        ->whereNull('read_at');
                },
            ])
            ->with([
                'users:id,first_name,last_name,avatar',
                'messages' => function ($query) {
                    $query->latest('id')->limit(1);
                },
            ])
            ->get()
            ->sortByDesc(function ($conversation) {
                return $conversation->messages->first()?->created_at ?? now();
            })
            ->values();

        $viewData = compact(
            'section',
            'box',
            'status',
            'counts',
            'requestCounts',
            'received',
            'sent',
            'conversations'
        );

        if ($request->ajax()) {
            return view('chat-requests.partials.body', $viewData);
        }

        return view('chat-requests.index', $viewData);
    }

    public function send(Request $request, User $user)
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        $input = $request->validate([
            'description' => 'required|string|max:5000',
            'request_to_group' => 'nullable|integer|exists:groups,id',
        ]);

        $currentUser = auth()->user();

        if ((int) $currentUser->id === (int) $user->id) {
            return back()->with('error', 'Invalid request');
        }

        if (!empty($input['request_to_group'])) {
            $isTargetManager = GroupUser::query()
                ->where('group_id', $input['request_to_group'])
                ->where('user_id', $user->id)
                ->where('role', 3)
                ->where('status', 1)
                ->exists();
            abort_unless($isTargetManager, 422, 'مدیر انتخاب‌شده عضو فعال این گروه نیست.');
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
                    'request_to_group' => $input['request_to_group'] ?? null,
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
            'request_to_group' => $input['request_to_group'] ?? null,
            'message' => $input['description'],
            'status' => 'pending',
        ]);

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

        if (! $this->canManageRequest($chatRequest, $currentUser)) {
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

        $originalSender = User::find($chatRequest->sender_id);
        if ($originalSender && (int) $originalSender->id !== (int) $currentUser->id) {
            Notification::send($originalSender, new ChatRequestAcceptedNotification(
                (int) $chatRequest->id,
                (int) $conversation->id,
                $currentUser->fullName()
            ));
        }

        return redirect()->route('private-chats.show', $conversation->id);
    }

    public function reject(ChatRequest $chatRequest)
    {
        $currentUser = auth()->user();

        if (! $this->canManageRequest($chatRequest, $currentUser)) {
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

    private function canManageRequest(ChatRequest $chatRequest, User $user): bool
    {
        if ((int) $chatRequest->receiver_id === (int) $user->id) {
            return true;
        }

        return $chatRequest->request_to_group && GroupUser::query()
            ->where('group_id', $chatRequest->request_to_group)
            ->where('user_id', $user->id)
            ->where('role', 3)
            ->where('status', 1)
            ->exists();
    }
}
