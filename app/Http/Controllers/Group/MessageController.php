<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Message;
use App\Models\PinnedMessage;
use App\Models\ReportedMessage;
use App\Models\Blog;
use App\Models\Poll;
use App\Models\User;
use App\Models\MessageReaction;
use App\Events\GroupMessageUpdated;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $storeT0 = microtime(true);
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T0 start store()');

        // Basic validation
        $basicRules = [
            'message' => 'nullable|string|max:2000',
            'group_id' => 'required|exists:groups,id',
            'parent_id' => 'nullable',
            'file' => 'nullable|file|max:20480', // 20MB max file size
            'voice_message' => 'nullable|file|max:10240', // 10MB max for voice
        ];
        


        $basicRules['message'] = 'nullable|string|max:2000';
        
        // Validate voice message separately with custom logic
        if ($request->hasFile('voice_message')) {
            $voiceFile = $request->file('voice_message');
            
            // Check file size
            if ($voiceFile->getSize() > 10 * 1024 * 1024) {
                return response()->json([
                    'An error occurred. Please try again.'
                ], 422);
            }
            
            // Check MIME type manually
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
                'application/octet-stream', // Fallback for some browsers
            ];
            
            $mimeType = $voiceFile->getMimeType();
            $extension = strtolower($voiceFile->getClientOriginalExtension());
            
            // Check if MIME type is allowed
            $isValidMime = in_array($mimeType, $allowedMimeTypes);
            
            // Also check extension as fallback
            $allowedExtensions = ['mp3', 'wav', 'ogg', 'webm', 'opus'];
            $isValidExtension = in_array($extension, $allowedExtensions);
            
            // If MIME type starts with 'audio/', accept it
            if (!$isValidMime && !$isValidExtension) {
                if (str_starts_with($mimeType, 'audio/')) {
                    $isValidMime = true; // Accept any audio/* MIME type
                }
            }
            
            if (!$isValidMime && !$isValidExtension) {
                return response()->json([
                    'An error occurred. Please try again.'
                ], 422);
            }
        } else {
            // If no voice file, use standard validation
            $basicRules['voice_message'] = 'nullable|file|max:10240';
        }
        
        
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T1 before validate: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        try {
            $inputs = $request->validate($basicRules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMessage = 'Validation failed. Please check your input.';
            
            // Extract first error message
            if (!empty($errors)) {
                $firstError = reset($errors);
                if (is_array($firstError) && !empty($firstError)) {
                    $errorMessage = $firstError[0];
                }
            }
            
            return response()->json([
                'status' => 'error',
                'message' => $errorMessage,
                'errors' => $errors
            ], 422);
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T2 after validate: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        try {
            $group = Group::findOrFail($request->group_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'An error occurred. Please try again.'
            ], 404);
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T3 after findOrFail: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        $group->update(['last_activity_at' => now()]);
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T4 after group update: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        $user = auth()->user();
        $groupUserRole = GroupUser::where('group_id', $group->id)->where('user_id', $user->id)->value('role');
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T5 after groupUserRole: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');

$member = $group->users()->whereKey($user->id)->exists();
\Illuminate\Support\Facades\Log::info('[STORE_TIMING] T6 after member check: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');

if (!$member || ($groupUserRole === 0 && $group->is_open == 0)) {
    return response()->json(['An error occurred. Please try again.'], 403);
}


        $key = 'send-message:' . $user->id . ':' . $group->id;
        $maxAttempts = 10;
        $decayMinutes = 1;
        
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Please wait ' . $seconds . ' seconds before sending another message.'
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
                return response()->json([
                    'An error occurred. Please try again.'
                ], 429);
            }
        }



        $messageText = trim($request->message ?? '');
        $hasVoiceMessage = $request->hasFile('voice_message');
        
        if (empty($messageText) && !$hasVoiceMessage) {
            return response()->json([
                'An error occurred. Please try again.'
            ], 422);
        }
        

        if (!empty($messageText)) {

            $messageText = e($messageText);

            $messageText = nl2br($messageText);
        }
        
        $messageData = [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'message' => $messageText ?: ($hasVoiceMessage ? 'Voice message' : ''),
            'parent_id' => $request->parent_id,
        ];

        // اگر parent_id عددی است، باید از جدول messages باشد
        // اگر پیام والد حذف شده، parent_id را null کن تا FK violation نشه
        $rawParentId = $request->parent_id;
        if ($rawParentId && is_numeric($rawParentId)) {
            $parentExists = Message::where('id', (int)$rawParentId)
                ->where('group_id', $group->id)
                ->exists();
            if (!$parentExists) {
                $messageData['parent_id'] = null;
                $rawParentId = null;
            }
        }

        // Handle threading: if parent_id exists, set thread_id
        if ($rawParentId && is_numeric($rawParentId)) {
            $parentMessage = Message::find((int)$rawParentId);
            if ($parentMessage) {
                // If parent has a thread_id, use it; otherwise, parent is the thread root
                $messageData['thread_id'] = $parentMessage->thread_id ?? $parentMessage->id;
                
                // Increment reply count on thread root
                $threadRoot = $parentMessage->thread_id 
                    ? Message::find($parentMessage->thread_id) 
                    : $parentMessage;
                
                if ($threadRoot) {
                    $threadRoot->incrementReplyCount();
                }
            }
        } elseif ($request->parent_id && !is_numeric($request->parent_id)) {
            // poll-X یا post-X: اینها string هستند و FK ندارند
            // هیچ threading logic نداریم برای اینها
        }

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/messages', $fileName, 'public');
            $messageData['file_path'] = $filePath;
            $messageData['file_type'] = $file->getMimeType();
            $messageData['file_name'] = $file->getClientOriginalName();
        }

        // Handle voice message upload
        if ($request->hasFile('voice_message')) {
            $voiceFile = $request->file('voice_message');
            
            // Validate file size (10MB max)
            if ($voiceFile->getSize() > 10 * 1024 * 1024) {
                return response()->json([
                    'An error occurred. Please try again.'
                ], 413);
            }
            
            // Get file extension - handle webm files properly
            $originalExtension = $voiceFile->getClientOriginalExtension();
            $mimeType = $voiceFile->getMimeType();
            
            // If extension is empty or not recognized, use mime type to determine extension
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
                    $originalExtension = 'webm'; // Default to webm for MediaRecorder
                }
            }
            
            $voiceFileName = 'voice_' . time() . '_' . uniqid() . '.' . $originalExtension;
            $voiceFilePath = $voiceFile->storeAs('uploads/voice_messages', $voiceFileName, 'public');
            $messageData['voice_message'] = $voiceFilePath;
            $messageData['file_type'] = $mimeType ?: 'audio/webm';
            $messageData['file_name'] = $voiceFile->getClientOriginalName() ?: 'voice_message.' . $originalExtension;
            
            // If no text message, set a default message
            if (empty($messageData['message'])) {
                $messageData['message'] = 'Voice message';
            }
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T7 before Message::create: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        $message = Message::create($messageData);
        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T8 after Message::create: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');

        $response = [
            'status' => 'success',
            'message' => [
                'id' => $message->id,
                'user_id' => $message->user_id,
                'message' => $message->message,
                'created_at' => $message->created_at->format('H:i'),
                'sender' => $user->first_name . ' ' . $user->last_name,
                'parent_id' => $message->parent_id,
                'file_path' => $message->file_path,
                'file_type' => $message->file_type,
                'file_name' => $message->file_name,
                'voice_message' => $message->voice_message ? (str_starts_with($message->voice_message, 'http') ? $message->voice_message : (function($path) {
                    $path = ltrim($path, '/');
                    $pathParts = explode('/', $path);
                    $encodedParts = array_map('rawurlencode', $pathParts);
                    return asset('storage/' . implode('/', $encodedParts));
                })($message->voice_message)) : null,
            ]
        ];
        
        if ($message->parent_id) {
            $parentId = $message->parent_id;
        
            if (Str::startsWith($parentId, 'poll-')) {
                $id = (int) Str::after($parentId, 'poll-');
                $parent = Poll::with('user')->find($id);
                $response['message']['parent_sender'] = $parent->user->first_name . ' ' . $parent->user->last_name;
                $response['message']['parent_content'] = $parent->title ?? $parent->question ?? '';
            } elseif (Str::startsWith($parentId, 'post-')) {
                $id = (int) Str::after($parentId, 'post-');
                $parent = Blog::with('user')->find($id);
                $response['message']['parent_sender'] = $parent->user->first_name . ' ' . $parent->user->last_name;
                $response['message']['parent_content'] = $parent->title ?? '';
            } else {
                    $parentMessage = Message::with('user')->find($message->parent_id);
                    $response['message']['parent_sender'] = $parentMessage->user->first_name . ' ' . $parentMessage->user->last_name;
                    $response['message']['parent_content'] = $parentMessage->message;
            }
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T9 response built: ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        // Dispatch event for notifications
        $this->dispatchGroupEvent(new \App\Events\MessageCreated($message, $group, $user));

        // Extract and process mentions (@user_id format)
        if ($request->message) {
            $this->processMentions($request->message, $message, $group, $user);
        }

        \Illuminate\Support\Facades\Log::info('[STORE_TIMING] T10 before return (total): ' . round((microtime(true) - $storeT0) * 1000) . 'ms');
        return response()->json($response);
    }

    public function edit(Request $request, Message $message)
    {
        $request->validate([
            'content' => 'required|string|max:2000',
        ]);





        $plainContent = $request->input('content');




        $escaped = e($plainContent);
        $htmlContent = nl2br($escaped);


        $message->update([
            'message'   => $htmlContent,
            'edited'    => true,
            'edited_by' => auth()->user()->id,
        ]);

        // اطلاع به سایر کاربران از طریق polling
        $this->dispatchGroupEvent(new GroupMessageUpdated(
            (int) $message->group_id,
            'edit',
            [
                'message_id' => (int) $message->id,
                'content'    => $htmlContent,
                'edited'     => true,
            ],
            (int) auth()->id()
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Message updated successfully.',
            'content' => $htmlContent,
            'edited'  => true,
            'message_id' => (int) $message->id,
        ]);
    }
    
    public function delete(Request $request, Message $message)
    {
        $expectsJson = $request->expectsJson() || $request->wantsJson() || $request->ajax()
            || str_contains((string) $request->header('Accept', ''), 'application/json');

        if (isset($_GET['admin'])) {
            if ($message->removed_by == null) {
                $message->removed_by = $_GET['admin'];
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

        $deletedId = (int) $message->id;
        $groupId = (int) $message->group_id;
        $actorId = (int) auth()->id();

        $message->delete();

        // ذخیره شناسه حذف‌شده در cache تا polling کاربران دیگه هم باخبر بشن
        $cacheKey = 'group.' . $groupId . '.deleted_ids';
        $deletedIds = Cache::get($cacheKey, []);
        $deletedIds[] = $deletedId;
        Cache::put($cacheKey, array_unique(array_slice($deletedIds, -50)), now()->addMinutes(5));

        event(new GroupMessageUpdated(
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

        // Can pin if manager (role 3) OR if it's their own message
        if ($groupUserRole !== 3 && $message->user_id !== $user->id) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        // Check if message is already pinned
        if (PinnedMessage::where('message_id', $message->id)->exists()) {
            return response()->json(['An error occurred. Please try again.'], 400);
        }

        PinnedMessage::create([
            'message_id' => $message->id,
            'group_id' => $group->id,
            'pinned_by' => $user->id
        ]);

        event(new GroupMessageUpdated(
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

        // Can unpin if manager (role 3) OR if it's their own message
        if ($groupUserRole !== 3 && $message->user_id !== $user->id) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        PinnedMessage::where('message_id', $message->id)->delete();

        event(new GroupMessageUpdated(
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
            'group_id' => 'required|exists:groups,id'
        ]);
        
        $message = Message::findOrFail($id);
        

        $existingReport = ReportedMessage::where('message_id', $id)
            ->where('reported_by', auth()->id())
            ->where(function($query) {
                $query->where('status', 'pending')
                      ->orWhere('status', 'pending_group_manager');
            })
            ->first();
        
        if ($existingReport) {
            return response()->json([
                'An error occurred. Please try again.'
            ], 400);
        }

        $report = ReportedMessage::create([
            'message_id' => $id,
            'reported_by' => auth()->id(),
            'group_id' => $validated['group_id'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending', // pending moderation
            'escalated_to_admin' => false
        ]);

        // Dispatch event for notifying managers and inspectors
        $group = \App\Models\Group::find($validated['group_id']);
        if ($group) {
            event(new \App\Events\MessageReported($report, $group, auth()->user()));
        }

        return response()->json([
            'Operation completed successfully.'
        ]);
    }
    
    
    
    
    public function search(Request $request, Group $group)
    {
        
        $q       = trim((string) $request->get('q', ''));
        $limit   = (int) min(max((int)$request->get('limit', 20), 5), 50);
        $page    = (int) max((int)$request->get('page', 1), 1);
        $offset  = ($page - 1) * $limit;
        
        // Advanced filters
        $userId = $request->get('user_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $hasFile = $request->get('has_file');
        $messageType = $request->get('type'); // 'message', 'post', 'poll', 'all'

        if ($q === '' && !$userId && !$dateFrom && !$dateTo && !$hasFile) {
            return response()->json([
                'items'    => [],
                'page'     => $page,
                'has_more' => false,
            ]);
        }


        $norm = static function(string $t) {
            $map = [
                '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4',
                '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9',
            ];
            return strtr($t, $map);
        };
        $qNorm = $q ? $norm($q) : '';
        $like  = $qNorm ? '%'.str_replace(['%','_'], ['\%','\_'], $qNorm).'%' : '%';


        $messages = collect();
        $blogs = collect();
        $polls = collect();

        // Messages
        if ($messageType === 'all' || $messageType === 'message' || !$messageType) {
            $messagesQuery = \App\Models\Message::query()
                ->leftJoin('users', 'users.id', '=', 'messages.user_id')
                ->where('messages.group_id', $group->id);

            if ($qNorm) {
                $messagesQuery->where('messages.message', 'like', $like);
            }

            if ($userId) {
                $messagesQuery->where('messages.user_id', $userId);
            }

            if ($dateFrom) {
                $messagesQuery->where('messages.created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $messagesQuery->where('messages.created_at', '<=', $dateTo);
            }

            if ($hasFile) {
                $messagesQuery->whereNotNull('messages.file_path');
            }

            $messages = $messagesQuery
                ->orderByDesc('messages.id')
                ->skip($offset)->take($limit)
                ->get([
                    'messages.id',
                    'messages.message',
                    'messages.created_at',
                    'messages.file_path',
                    'users.first_name',
                    'users.last_name',
                ])
                ->map(function($row) use ($qNorm) {
                    $sender = trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: 'Unknown user';
                    return [
                        'type'    => 'message',
                        'id'      => $row->id,
                        'title'   => $sender,
                        'snippet' => $this->makeSnippet($row->message ?? '', $qNorm ?: ''),
                        'date'    => optional($row->created_at)->format('Y-m-d H:i'),
                        'url'     => '#msg-'.$row->id,
                        'has_file' => !empty($row->file_path),
                    ];
                })
                ->values()
                ->toBase();
        }

        // Blogs
        if ($messageType === 'all' || $messageType === 'post' || !$messageType) {
            $blogsQuery = \App\Models\Blog::query()
                ->where('group_id', $group->id);

            if ($qNorm) {
                $blogsQuery->where(function($w) use ($like){
                    $w->where('title','like',$like)->orWhere('content','like',$like);
                });
            }

            if ($userId) {
                $blogsQuery->where('user_id', $userId);
            }

            if ($dateFrom) {
                $blogsQuery->where('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $blogsQuery->where('created_at', '<=', $dateTo);
            }

            $blogs = $blogsQuery
                ->orderByDesc('id')
                ->skip($offset)->take($limit)
                ->get(['id','title','content','created_at'])
                ->map(fn($b) => [
                    'type'=>'post',
                    'id'=>$b->id,
                    'title'=>$b->title ?: 'Post',
                    'snippet'=>$this->makeSnippet($b->content ?? '', $qNorm ?: ''),
                    'date'=>optional($b->created_at)->format('Y-m-d H:i'),
                    'url'=>'#blog-'.$b->id,
                ])
                ->values()
                ->toBase();
        }

        // Polls
        if ($messageType === 'all' || $messageType === 'poll' || !$messageType) {
            $pollsQuery = \App\Models\Poll::query()
                ->where('group_id', $group->id);

            if ($qNorm) {
                $pollsQuery->where(function($w) use ($like){
                    $w->where('question','like',$like);
                });
            }

            if ($userId) {
                $pollsQuery->where('created_by', $userId);
            }

            if ($dateFrom) {
                $pollsQuery->where('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $pollsQuery->where('created_at', '<=', $dateTo);
            }

            $polls = $pollsQuery
                ->orderByDesc('id')
                ->skip($offset)->take($limit)
                ->get(['id','question','created_at'])
                ->map(function($p) use ($qNorm){
                    $text = $p->question ?: $p->question;
                    return [
                        'type'=>'poll',
                        'id'=>$p->id,
                        'title'=>$p->question ?: 'Poll',
                        'snippet'=>$this->makeSnippet($text ?? '', $qNorm ?: ''),
                        'date'=>optional($p->created_at)->format('Y-m-d H:i'),
                        'url'=>'#poll-'.$p->id,
                    ];
                })
                ->values()
                ->toBase();
        }


        $items  = $messages->merge($blogs)->merge($polls)->sortByDesc('date')->values();
        $slice  = $items->slice(0, $limit)->values();
        $hasMore = $items->count() > $limit;


        return response()->json([
            'items'    => $slice,
            'page'     => $page,
            'has_more' => $hasMore,
        ]);
    }

    private function makeSnippet(string $htmlOrText, string $needle, int $radius = 40): string
    {
        $plain = trim(strip_tags($htmlOrText));
        $plain = preg_replace('/\s+/u', ' ', $plain);
        $pos = mb_stripos($plain, $needle);
        if ($pos === false) {
            return mb_substr($plain, 0, 80).'...';
        }
        $start = max(0, $pos - $radius);
        $len   = mb_strlen($needle);
        $before = mb_substr($plain, $start, $pos - $start);
        $match  = mb_substr($plain, $pos, $len);
        $after  = mb_substr($plain, $pos + $len, $radius);
        $prefix = $start > 0 ? '...' : '';
        $suffix = ($pos + $len + $radius) < mb_strlen($plain) ? '...' : '';
        return $prefix.$before.'<mark>'.$match.'</mark>'.$after.$suffix;
    }

    /**
     * Process mentions in message (@user_id format)
     */
    private function processMentions(string $messageText, Message $message, Group $group, User $sender): void
    {
        // Pattern: @[user_id] or @user_id
        preg_match_all('/@\[?(\d+)\]?/', $messageText, $matches);
        
        if (empty($matches[1])) {
            return;
        }

        $mentionedUserIds = array_unique($matches[1]);
        
        foreach ($mentionedUserIds as $userId) {
            $mentionedUser = User::find($userId);
            
            if ($mentionedUser && $mentionedUser->id !== $sender->id) {
                // Check if user is member of the group
                $isMember = $group->users()->whereKey($mentionedUser->id)->exists();
                
                if ($isMember) {
                    $this->dispatchGroupEvent(new \App\Events\UserMentioned($mentionedUser, $message, $group, $sender));
                }
            }
        }
    }

    /**
     * Search users for mention autocomplete
     */
    public function searchUsersForMention(Request $request, Group $group)
    {
        $query = trim($request->get('q', ''));
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }


        $users = $group->users()
            ->where(function($q) use ($query) {
                $q->where('first_name', 'like', '%' . $query . '%')
                  ->orWhere('last_name', 'like', '%' . $query . '%')
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $query . '%']);
            })
            ->where('group_user.status', 1) // active users only
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.avatar')
            ->limit(10)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'avatar' => $user->avatar,
                    'display' => '@[' . $user->id . ']' . $user->first_name . ' ' . $user->last_name,
                ];
            });

        return response()->json($users);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Message $message)
    {
        $user = auth()->user();
        
        // Check if user is member of the group
        $isMember = $message->group->users()->whereKey($user->id)->exists();
        if (!$isMember) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        // Don't mark own messages as read
        if ($message->user_id === $user->id) {
            return response()->json(['Operation completed successfully.']);
        }

        $message->markAsRead($user->id);

        return response()->json([
            'Operation completed successfully.',
            'read_count' => $message->read_count
        ]);
    }

    /**
     * Mark multiple messages as read
     */
    public function markMultipleAsRead(Request $request, Group $group)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id'
        ]);

        $user = auth()->user();
        $messageIds = $request->message_ids;

        // Verify all messages belong to this group
        $messages = Message::whereIn('id', $messageIds)
            ->where('group_id', $group->id)
            ->where('user_id', '!=', $user->id) // Don't mark own messages
            ->get();

        $count = 0;
        foreach ($messages as $message) {
            if (!$message->isReadBy($user->id)) {
                $message->markAsRead($user->id);
                $count++;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => $count . ' members found.',
            'count' => $count
        ]);
    }

    /**
     * Update last read message ID for user in group
     */
    public function updateLastReadMessage(Request $request, Group $group)
    {
        $request->validate([
            'message_id' => 'required|exists:messages,id'
        ]);

        $user = auth()->user();
        
        // Verify message belongs to this group
        $message = Message::where('id', $request->message_id)
            ->where('group_id', $group->id)
            ->first();
        
        if (!$message) {
            return response()->json([
                'An error occurred. Please try again.'
            ], 404);
        }

        // Check if user is member of the group
        $groupUser = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();
        
        if (!$groupUser) {
            return response()->json([
                'An error occurred. Please try again.'
            ], 403);
        }

        // Update last_read_message_id
        $groupUser->update(['last_read_message_id' => $request->message_id]);

        // Mark message and all previous messages as read
        $group->messages()
            ->where('id', '<=', $request->message_id)
            ->where('user_id', '!=', $user->id) // Don't mark own messages
            ->get()
            ->each(function ($msg) use ($user) {
                if (!$msg->isReadBy($user->id)) {
                    $msg->markAsRead($user->id);
                }
            });

        return response()->json([
            'Operation completed successfully.'
        ]);
    }

    /**
     * Handle typing indicator
     */
    public function typing(Request $request, Group $group)
    {
        $request->validate([
            'is_typing' => 'required|boolean'
        ]);

        $user = auth()->user();
        
        // Check if user is member of the group
        $isMember = $group->users()->whereKey($user->id)->exists();
        if (!$isMember) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        event(new \App\Events\UserTyping($user, $group, $request->is_typing));

        return response()->json([
            'status' => 'success'
        ]);
    }

    /**
     * Toggle reaction on message
     */
    public function toggleReaction(Request $request, Message $message)
    {
        $request->validate([
            'reaction_type' => 'required|string|in:like,love,laugh,angry,sad,wow'
        ]);

        $user = auth()->user();
        
        // Check if user is member of the group
        $isMember = $message->group->users()->whereKey($user->id)->exists();
        if (!$isMember) {
            return response()->json(['An error occurred. Please try again.'], 403);
        }

        $reactionType = $request->reaction_type;
        
        // Check if reaction already exists
        $existingReaction = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('reaction_type', $reactionType)
            ->first();

        if ($existingReaction) {
            // Remove reaction
            $existingReaction->delete();
            $action = 'removed';
        } else {
            // Remove other reactions from this user on this message (only one reaction per user)
            MessageReaction::where('message_id', $message->id)
                ->where('user_id', $user->id)
                ->delete();
            
            // Add new reaction
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'reaction_type' => $reactionType
            ]);
            $action = 'added';
        }

        // Get updated reactions
        $reactions = $message->fresh()->reactions()
            ->with('user:id,first_name,last_name,avatar')
            ->get()
            ->groupBy('reaction_type')
            ->map(function($group) {
                return [
                    'type' => $group->first()->reaction_type,
                    'count' => $group->count(),
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

        event(new GroupMessageUpdated(
            (int) $message->group_id,
            'reaction',
            [
                'message_id' => (int) $message->id,
                'reactions' => $reactions->toArray(),
                'reaction_type' => $reactionType,
                'action' => $action,
            ],
            (int) $user->id
        ));

        return response()->json([
            'status' => 'success',
            'action' => $action,
            'reactions' => $reactions
        ]);
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
                    $voiceMessage = asset('storage/' . implode('/', $encodedParts));
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

    private function dispatchGroupEvent(object $event): void
    {
        if ((bool) config('group-chat.defer_broadcasts', true)) {
            dispatch(static fn () => event($event))->afterResponse();
            return;
        }

        event($event);
    }
}




