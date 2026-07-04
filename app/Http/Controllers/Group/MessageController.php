<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupMessageUpdated;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\PinnedMessage;
use App\Models\Poll;
use App\Models\ReportedMessage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $storeT0 = microtime(true);
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T0 start store()');

        $basicRules = [
            'message' => 'nullable|string|max:2000',
            'group_id' => 'required|exists:groups,id',
            'parent_id' => 'nullable',
            'client_message_id' => 'nullable|string|max:100',
            'file' => 'nullable|file|max:20480',
            'voice_message' => 'nullable|file|max:10240',
        ];

        if ($request->hasFile('voice_message')) {
            $voiceFile = $request->file('voice_message');

            if ($voiceFile->getSize() > 10 * 1024 * 1024) {
                return response()->json(['An error occurred. Please try again.'], 422);
            }

            $allowedMimeTypes = [
                'audio/mpeg',
                'audio/mp3',
                'audio/wav',
                'audio/wave',
                'audio/x-wav',
                'audio/ogg',
                'audio/webm',
                'audio/x-webm',
                'audio/opus',
                'application/octet-stream',
            ];

            $mimeType = $voiceFile->getMimeType();
            $extension = strtolower($voiceFile->getClientOriginalExtension());
            $isValidMime = in_array($mimeType, $allowedMimeTypes, true);
            $allowedExtensions = ['mp3', 'wav', 'ogg', 'webm', 'opus'];
            $isValidExtension = in_array($extension, $allowedExtensions, true);

            if (! $isValidMime && ! $isValidExtension && str_starts_with((string) $mimeType, 'audio/')) {
                $isValidMime = true;
            }

            if (! $isValidMime && ! $isValidExtension) {
                return response()->json(['An error occurred. Please try again.'], 422);
            }
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T1 before validate: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        try {
            $request->validate($basicRules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMessage = 'Validation failed. Please check your input.';

            if (! empty($errors)) {
                $firstError = reset($errors);
                if (is_array($firstError) && ! empty($firstError)) {
                    $errorMessage = $firstError[0];
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => $errorMessage,
                'errors' => $errors,
            ], 422);
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T2 after validate: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        try {
            $group = Group::findOrFail($request->group_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['An error occurred. Please try again.'], 404);
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T3 after findOrFail: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        $group->update(['last_activity_at' => now()]);
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T4 after group update: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $group->id)->where('user_id', $user->id)->value('role');
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T5 after groupUserRole: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');

        $member = $group->users()->whereKey($user->id)->exists();
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T6 after member check: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');

        if (! $member || ($groupUserRole === 0 && $group->is_open == 0)) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $key = 'send-message:' . $user->id . ':' . $group->id;
        $maxAttempts = 10;
        $decayMinutes = 1;

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Please wait ' . $seconds . ' seconds before sending another message.',
            ], 429);
        }

        \Illuminate\Support\Facades\RateLimiter::hit($key, $decayMinutes * 60);

        if ($request->message) {
            $recentMessages = Message::where('user_id', $user->id)
                ->where('group_id', $group->id)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->pluck('message')
                ->toArray();

            $currentMessage = trim($request->message);
            $similarCount = 0;
            foreach ($recentMessages as $recent) {
                similar_text($currentMessage, trim($recent), $percent);
                if ($percent > 80) {
                    $similarCount++;
                }
            }

            if ($similarCount >= 2) {
                return response()->json(['An error occurred. Please try again.'], 429);
            }
        }

        $messageText = trim($request->message ?? '');
        $hasVoiceMessage = $request->hasFile('voice_message');

        if (empty($messageText) && ! $hasVoiceMessage) {
            return response()->json(['An error occurred. Please try again.'], 422);
        }

        if (! empty($messageText)) {
            $messageText = nl2br(e($messageText));
        }

        $clientMessageId = trim((string) $request->input('client_message_id', ''));
        if ($clientMessageId !== '') {
            $existingMessage = Message::where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->where('client_message_id', $clientMessageId)
                ->first();

            if ($existingMessage) {
                return response()->json([
                    'status' => 'success',
                    'message' => $this->buildStoredMessagePayload($existingMessage->loadMissing('user'), $user),
                    'idempotent' => true,
                ]);
            }
        }

        $messageData = [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'message' => $messageText ?: ($hasVoiceMessage ? 'Voice message' : ''),
            'parent_id' => $request->parent_id,
            'client_message_id' => $clientMessageId !== '' ? $clientMessageId : null,
        ];

        $rawParentId = $request->parent_id;
        if ($rawParentId && is_numeric($rawParentId)) {
            $parentExists = Message::where('id', (int) $rawParentId)
                ->where('group_id', $group->id)
                ->exists();
            if (! $parentExists) {
                $messageData['parent_id'] = null;
                $rawParentId = null;
            }
        }

        if ($rawParentId && is_numeric($rawParentId)) {
            $parentMessage = Message::find((int) $rawParentId);
            if ($parentMessage) {
                $messageData['thread_id'] = $parentMessage->thread_id ?? $parentMessage->id;

                $threadRoot = $parentMessage->thread_id ? Message::find($parentMessage->thread_id) : $parentMessage;
                if ($threadRoot) {
                    $threadRoot->incrementReplyCount();
                }
            }
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/messages', $fileName, 'public');
            $messageData['file_path'] = $filePath;
            $messageData['file_type'] = $file->getMimeType();
            $messageData['file_name'] = $file->getClientOriginalName();
        }

        if ($request->hasFile('voice_message')) {
            $voiceFile = $request->file('voice_message');
            if ($voiceFile->getSize() > 10 * 1024 * 1024) {
                return response()->json(['An error occurred. Please try again.'], 413);
            }

            $originalExtension = $voiceFile->getClientOriginalExtension();
            $mimeType = $voiceFile->getMimeType();

            if (empty($originalExtension) || $originalExtension === 'bin') {
                if (str_contains($mimeType, 'webm') || str_contains($mimeType, 'opus')) {
                    $originalExtension = 'webm';
                } elseif (str_contains($mimeType, 'ogg')) {
                    $originalExtension = 'ogg';
                } elseif (str_contains($mimeType, 'wav')) {
                    $originalExtension = 'wav';
                } elseif (str_contains($mimeType, 'mpeg') || str_contains($mimeType, 'mp3')) {
                    $originalExtension = 'mp3';
                } else {
                    $originalExtension = 'webm';
                }
            }

            $voiceFileName = 'voice_' . time() . '_' . uniqid() . '.' . $originalExtension;
            $voiceFilePath = $voiceFile->storeAs('uploads/voice_messages', $voiceFileName, 'public');
            $messageData['voice_message'] = $voiceFilePath;
            $messageData['file_type'] = $mimeType ?: 'audio/webm';
            $messageData['file_name'] = $voiceFile->getClientOriginalName() ?: 'voice_message.' . $originalExtension;

            if (empty($messageData['message'])) {
                $messageData['message'] = 'Voice message';
            }
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T7 before Message::create: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        try {
            $message = Message::create($messageData);
        } catch (QueryException $exception) {
            if ($clientMessageId !== '' && $this->isUniqueIdempotencyConflict($exception)) {
                $existingMessage = Message::where('group_id', $group->id)
                    ->where('user_id', $user->id)
                    ->where('client_message_id', $clientMessageId)
                    ->first();

                if ($existingMessage) {
                    return response()->json([
                        'status' => 'success',
                        'message' => $this->buildStoredMessagePayload($existingMessage->loadMissing('user'), $user),
                        'idempotent' => true,
                    ]);
                }
            }

            throw $exception;
        }
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T8 after Message::create: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');

        $response = [
            'status' => 'success',
            'message' => $this->buildStoredMessagePayload($message->loadMissing('user'), $user),
        ];

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T9 response built: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        $this->dispatchGroupEvent(new \App\Events\MessageCreated($message, $group, $user));

        if ($request->message) {
            $this->processMentions($request->message, $message, $group, $user);
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T10 before return (total): ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        return response()->json($response);
    }

    private function buildStoredMessagePayload(Message $message, User $user): array
    {
        $payload = [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'message' => $message->message,
            'created_at' => $message->created_at->format('H:i'),
            'sender' => $user->first_name . ' ' . $user->last_name,
            'parent_id' => $message->parent_id,
            'file_path' => $message->file_path,
            'file_type' => $message->file_type,
            'file_name' => $message->file_name,
            'voice_message' => $message->voice_message ? (str_starts_with($message->voice_message, 'http') ? $message->voice_message : (function ($path) {
                $path = ltrim($path, '/');
                $pathParts = explode('/', $path);
                $encodedParts = array_map('rawurlencode', $pathParts);
                return asset('storage/' . implode('/', $encodedParts));
            })($message->voice_message)) : null,
        ];

        if ($message->parent_id) {
            $parentId = $message->parent_id;

            if (Str::startsWith($parentId, 'poll-')) {
                $id = (int) Str::after($parentId, 'poll-');
                $parent = Poll::with('user')->find($id);
                if ($parent && $parent->user) {
                    $payload['parent_sender'] = $parent->user->first_name . ' ' . $parent->user->last_name;
                    $payload['parent_content'] = $parent->title ?? $parent->question ?? '';
                }
            } elseif (Str::startsWith($parentId, 'post-')) {
                $id = (int) Str::after($parentId, 'post-');
                $parent = Blog::with('user')->find($id);
                if ($parent && $parent->user) {
                    $payload['parent_sender'] = $parent->user->first_name . ' ' . $parent->user->last_name;
                    $payload['parent_content'] = $parent->title ?? '';
                }
            } else {
                $parentMessage = Message::with('user')->find($message->parent_id);
                if ($parentMessage && $parentMessage->user) {
                    $payload['parent_sender'] = $parentMessage->user->first_name . ' ' . $parentMessage->user->last_name;
                    $payload['parent_content'] = $parentMessage->message;
                }
            }
        }

        return $payload;
    }

    private function isUniqueIdempotencyConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;

        return in_array($sqlState, ['23000', '23505'], true) || in_array($driverCode, [1062, 19, 2067], true);
    }

    private function processMentions(string $messageText, Message $message, Group $group, User $user): void
    {
        if ($messageText === '') {
            return;
        }

        preg_match_all('/@([0-9]+)/', $messageText, $matches);
        if (empty($matches[1])) {
            return;
        }
    }

    /**
     * Get message reactions
     */
    public function getReactions(Message $message)
    {
        $user = auth()->user();
        
        // Check if user is member of the group
        $isMember = $message->group->users()->whereKey($user->id)->exists();
        if (!$isMember) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $reactions = $message->reactions()
            ->with('user:id,first_name,last_name,avatar')
            ->get()
            ->groupBy('reaction_type')
            ->map(function($group) use ($user) {
                $userReaction = $group->firstWhere('user_id', $user->id);
                return [
                    'type' => $group->first()->reaction_type,
                    'count' => $group->count(),
                    'has_reacted' => $userReaction !== null,
                    'users' => $group->map(function($reaction) {
                        return [
                            'id' => $reaction->user->id,
                            'name' => $reaction->user->first_name . ' ' . $reaction->user->last_name,
                            'avatar' => $reaction->user->avatar
                        ];
                    })
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'reactions' => $reactions
        ]);
    }

    /**
     * Get thread replies
     */
    public function getThreadReplies(Message $message)
    {
        $user = auth()->user();
        
        // Check if user is member of the group
        $isMember = $message->group->users()->whereKey($user->id)->exists();
        if (!$isMember) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        // Get thread root (if this message is a reply, get its thread root)
        $threadRoot = $message->thread_id ? $message->thread : $message;
        
        // Get all replies in the thread
        $replies = $threadRoot->replies()
            ->with('user:id,first_name,last_name,avatar')
            ->get()
->map(function($reply) {
    $voiceMessage = $reply->voice_message;
    if ($voiceMessage && !str_starts_with($voiceMessage, 'http')) {
        $voicePath = ltrim($voiceMessage, '/');
        // Encode each part of the path to handle spaces and special characters
        $pathParts = explode('/', $voicePath);
        $encodedParts = array_map('rawurlencode', $pathParts);
        $voiceMessage = asset('storage/' . implode('/', $pathParts));
    }
    return [
        'id' => $reply->id,
        'user_id' => $reply->user_id,
        'message' => $reply->message,
        'sender' => $reply->user->first_name . ' ' . $reply->user->last_name,
        'avatar' => $reply->user->avatar,
        'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
        'file_path' => $reply->file_path,
        'file_type' => $reply->file_type,
        'voice_message' => $voiceMessage,
    ];
});

        return response()->json([
            'status' => 'success',
            'thread_root' => [
                'id' => $threadRoot->id,
                'message' => $threadRoot->message,
                'sender' => $threadRoot->user->first_name . ' ' . $threadRoot->user->last_name,
                'created_at' => $threadRoot->created_at->format('Y-m-d H:i:s'),
            ],
            'replies' => $replies,
            'reply_count' => $threadRoot->reply_count
        ]);
    }

    public function edit(Request $request, Message $message)
    {
        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $message->group_id)
            ->where('user_id', $user->id)
            ->value('role');

        $canEdit = (int) $message->user_id === (int) $user->id
            || $user->is_admin
            || $user->hasRole('super-admin')
            || (int) $groupUserRole === 3;

        if (! $canEdit) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $htmlContent = nl2br(e($request->input('content')));

        $message->update([
            'message' => $htmlContent,
            'edited' => true,
            'edited_by' => $user->id,
        ]);

        $this->dispatchGroupEvent(new GroupMessageUpdated(
            (int) $message->group_id,
            'edit',
            [
                'message_id' => (int) $message->id,
                'content' => $htmlContent,
                'edited' => true,
            ],
            (int) $user->id
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Message updated successfully.',
            'content' => $htmlContent,
            'edited' => true,
            'message_id' => (int) $message->id,
        ]);
    }

    public function delete(Request $request, Message $message)
    {
        $expectsJson = $request->expectsJson() || $request->wantsJson() || $request->ajax()
            || str_contains((string) $request->header('Accept', ''), 'application/json');

        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $message->group_id)
            ->where('user_id', $user->id)
            ->value('role');
        $canModerate = $user->is_admin || $user->hasRole('super-admin') || (int) $groupUserRole === 3;
        $canDeleteOwnMessage = (int) $message->user_id === (int) $user->id;

        if ($request->filled('admin')) {
            if (! $canModerate) {
                return response()->json(['An error occurred. Please try again.'], 403);
            }

            if ($message->removed_by == null) {
                $message->removed_by = (int) $user->id;
                $message->save();

                if ($expectsJson) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Message hidden by admin.',
                        'removed' => true,
                        'message_id' => $message->id,
                    ]);
                }

                return back()->with('success', 'Operation completed successfully.');
            }

            $message->removed_by = null;
            $message->save();

            if ($expectsJson) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Message restored.',
                    'removed' => false,
                    'message_id' => $message->id,
                ]);
            }

            return back()->with('success', 'Operation completed successfully.');
        }

        if (! $canDeleteOwnMessage && ! $canModerate) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $deletedId = (int) $message->id;
        $groupId = (int) $message->group_id;
        $actorId = (int) $user->id;

        if ($message->thread_id) {
            $threadRoot = $message->thread()->first();
            if ($threadRoot) {
                $threadRoot->decrementReplyCount();
            }
        }

        $message->delete();

        $cacheKey = 'group.' . $groupId . '.deleted_ids';
        $deletedIds = Cache::get($cacheKey, []);
        $deletedIds[] = $deletedId;
        Cache::put($cacheKey, array_unique(array_slice($deletedIds, -50)), now()->addMinutes(5));

        $this->dispatchGroupEvent(new GroupMessageUpdated(
            $groupId,
            'delete',
            [
                'message_id' => $deletedId,
            ],
            $actorId
        ));

        if ($expectsJson) {
            return response()->json([
                'status' => 'success',
                'message' => 'Message deleted successfully.',
                'deleted' => true,
                'message_id' => $deletedId,
            ]);
        }

        return back()->with('success', 'Operation completed successfully.');
    }

    public function pin(Message $message)
    {
        $group = $message->group;
        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->value('role');

        if ($groupUserRole !== 3 && $message->user_id !== $user->id) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        if (PinnedMessage::where('message_id', $message->id)->exists()) {
            return response()->json(['An error occurred. Please try again.'], 400);
        }

        PinnedMessage::create([
            'message_id' => $message->id,
            'group_id' => $group->id,
            'pinned_by' => $user->id,
        ]);

        $this->dispatchGroupEvent(new GroupMessageUpdated(
            (int) $group->id,
            'pin',
            [
                'message_id' => (int) $message->id,
                'pinned' => true,
                'pinned_count' => (int) PinnedMessage::where('group_id', $group->id)->count(),
            ],
            (int) $user->id
        ));

        return response()->json(['Operation completed successfully.']);
    }

    public function unpin(Message $message)
    {
        $group = $message->group;
        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->value('role');

        if ($groupUserRole !== 3 && $message->user_id !== $user->id) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        PinnedMessage::where('message_id', $message->id)->delete();

        $this->dispatchGroupEvent(new GroupMessageUpdated(
            (int) $group->id,
            'pin',
            [
                'message_id' => (int) $message->id,
                'pinned' => false,
                'pinned_count' => (int) PinnedMessage::where('group_id', $group->id)->count(),
            ],
            (int) $user->id
        ));

        return response()->json(['Operation completed successfully.']);
    }

    public function report(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'description' => 'nullable|string|max:1000',
            'group_id' => 'required|exists:groups,id',
        ]);

        $message = Message::findOrFail($id);

        $existingReport = ReportedMessage::where('message_id', $id)
            ->where('reported_by', auth()->id())
            ->where(function ($query) {
                $query->where('status', 'pending')
                    ->orWhere('status', 'pending_group_manager');
            })
            ->first();

        if ($existingReport) {
            return response()->json(['An error occurred. Please try again.'], 400);
        }

        $report = ReportedMessage::create([
            'message_id' => $id,
            'reported_by' => auth()->id(),
            'group_id' => $validated['group_id'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'escalated_to_admin' => false,
        ]);

        $group = Group::find($validated['group_id']);
        if ($group) {
            event(new \App\Events\MessageReported($report, $group, auth()->user()));
        }

        return response()->json(['Operation completed successfully.']);
    }

    public function toggleReaction(Request $request, Message $message)
    {
        $request->validate([
            'reaction_type' => 'required|string|max:10|in:' . implode(',', MessageReaction::REACTIONS),
        ]);

        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $message->group_id)
            ->where('user_id', $user->id)
            ->value('role');

        $isMember = $message->group->users()->whereKey($user->id)->exists();
        if (! $isMember || ($groupUserRole === 0 && $message->group->is_open == 0)) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $reactionType = $request->input('reaction_type');
        $existingReaction = MessageReaction::where([
            'message_id' => $message->id,
            'user_id' => $user->id,
        ])->first();

        if ($existingReaction && $existingReaction->reaction_type === $reactionType) {
            $existingReaction->delete();
        } elseif ($existingReaction) {
            $existingReaction->update(['reaction_type' => $reactionType]);
        } else {
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'reaction_type' => $reactionType,
            ]);
        }

        $reactions = MessageReaction::where([
            'message_id' => $message->id,
        ])
            ->with('user:id,first_name,last_name,avatar')
            ->get()
            ->groupBy('reaction_type')
            ->map(function ($group) {
                return [
                    'type' => $group->first()->reaction_type,
                    'count' => $group->count(),
                    'users' => $group->map(function ($reaction) {
                        return [
                            'id' => $reaction->user->id,
                            'name' => $reaction->user->first_name . ' ' . $reaction->user->last_name,
                            'avatar' => $reaction->user->avatar
                        ];
                    }),
                ];
            })
            ->values();

        $this->dispatchGroupEvent(new GroupMessageUpdated(
            (int) $message->group_id,
            'reaction',
            [
                'message_id' => (int) $message->id,
                'reactions' => $reactions->toArray(),
                'reaction_type' => $reactionType,
            ],
            (int) $user->id
        ));

        return response()->json([
            'status' => 'success',
            'reactions' => $reactions,
        ]);
    }

    /**
     * Mark a message as read
     */
    public function markAsRead(Request $request, Message $message)
    {
        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $message->group_id)
            ->where('user_id', $user->id)
            ->value('role');

        $isMember = $message->group->users()->whereKey($user->id)->exists();
        $canMark = $isMember || $user->is_admin || $user->hasRole('super-admin') || (int) $groupUserRole === 3;

        if (! $canMark) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $message->update(['read_at' => now()]);

        $this->dispatchGroupEvent(new GroupMessageUpdated(
            (int) $message->group_id,
            'mark-read',
            [
                'message_id' => (int) $message->id,
                'read' => true,
            ],
            (int) $user->id
        ));

        return response()->json(['status' => 'success', 'message' => 'Message marked as read.']);
    }

    /**
     * Update last read message timestamp
     */
    public function updateLastReadMessage(Request $request, Group $group)
    {
        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->value('role');

        $isMember = $group->users()->whereKey($user->id)->exists();
        $canUpdate = $isMember || $user->is_admin || $user->hasRole('super-admin') || (int) $groupUserRole === 3;

        if (! $canUpdate) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $group->update(['last_read_at' => now()]);

        return response()->json(['status' => 'success', 'message' => 'Last read timestamp updated.']);
    }

    /**
     * Send typing indicator
     */
    public function typing(Request $request, Group $group)
    {
        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->value('role');

        $isMember = $group->users()->whereKey($user->id)->exists();
        if (! $isMember || ($groupUserRole === 0 && $group->is_open == 0)) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $this->dispatchGroupEvent(new GroupMessageUpdated(
            (int) $group->id,
            'typing',
            [
                'user_id' => (int) $user->id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
            ],
            (int) $user->id
        ));

        return response()->json(['status' => 'success']);
    }

    /**
     * Search users for mention
     */
    public function searchUsersForMention(Request $request, Group $group)
    {
        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->value('role');

        $isMember = $group->users()->whereKey($user->id)->exists();
        if (! $isMember || ($groupUserRole === 0 && $group->is_open == 0)) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $query = $request->input('query', '');
        $users = User::where(function ($q) use ($query) {
            $q->where('first_name', 'like', '%' . $query . '%')
              ->orWhere('last_name', 'like', '%' . $query . '%')
              ->orWhere('national_id', 'like', '%' . $query . '%');
        })
        ->limit(10)
        ->get(['id', 'first_name', 'last_name', 'avatar']);

        return response()->json([
            'status' => 'success',
            'users' => $users->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->first_name . ' ' . $u->last_name,
                    'avatar' => $u->avatar,
                ];
            })
        ]);
    }

    /**
     * Search messages in group
     */
    public function search(Request $request, Group $group)
    {
        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->value('role');

        $isMember = $group->users()->whereKey($user->id)->exists();
        if (! $isMember || ($groupUserRole === 0 && $group->is_open == 0)) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $query = $request->input('query', '');
        $messages = Message::where('group_id', $group->id)
            ->where('message', 'like', '%' . $query . '%')
            ->with('user:id,first_name,last_name,avatar')
            ->limit(20)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'message' => $m->message,
                    'sender' => $m->user->first_name . ' ' . $m->user->last_name,
                    'created_at' => $m->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'status' => 'success',
            'messages' => $messages
        ]);
    }

    private function dispatchGroupEvent(object $event): void
    {
        if (! (bool) config('group-chat.enabled', true)) {
            return;
        }

        if (strtolower((string) config('group-chat.transport', 'auto')) === 'polling') {
            return;
        }

        if ((bool) config('group-chat.defer_broadcasts', true)) {
            dispatch(static function () use ($event): void {
                try {
                    event($event);
                } catch (\Throwable $exception) {
                    \Illuminate\Support\Facades\Log::warning('group_chat_broadcast_failed', [
                        'event' => get_class($event),
                        'message' => $exception->getMessage(),
                    ]);
                }
            })->afterResponse();
            return;
        }

        try {
            event($event);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('group_chat_broadcast_failed', [
                'event' => get_class($event),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}