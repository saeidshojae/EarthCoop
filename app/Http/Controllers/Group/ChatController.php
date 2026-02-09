<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\CategoryGroupSetting;
use App\Models\Candidate;
use App\Models\Delegation;
use App\Models\Election;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\OccupationalField;
use App\Models\ExperienceField;
use App\Models\ChatRequest;
use App\Models\Poll;
use App\Models\User;
use App\Models\Vote;
use App\Models\PinnedMessage;
use App\Models\ReportedMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function chat(Group $group)
    {
        $groupUser = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->first();
        
        // تعیین نقش بر اساس location_level:
        // - سطح محله و پایین‌تر (neighborhood, street, alley) → فعال (role 1)
        // - سطح منطقه و بالاتر (region, village, rural, city و ...) → ناظر (role 0)
        // اگر role در pivot وجود داشت و معتبر بود (2, 3, 4, 5)، از همان استفاده می‌کنیم
        $pivotRole = $groupUser ? (int)$groupUser->role : null;
        
        if (in_array($pivotRole, [2, 3, 4, 5], true)) {
            // نقش‌های خاص (بازرس، مدیر، مهمان، فعال۲) از pivot استفاده می‌شوند
            $yourRole = $pivotRole;
        } else {
            // در غیر این صورت، بر اساس location_level تعیین می‌کنیم
            $locationLevel = strtolower(trim((string)($group->location_level ?? '')));
            if (in_array($locationLevel, ['neighborhood', 'street', 'alley'], true)) {
                $yourRole = 1; // عضو فعال
            } else {
                $yourRole = 0; // ناظر
            }
        }
        
        $lastReadMessageId = $groupUser ? $groupUser->last_read_message_id : null;

        $messages = $group->messages()
            ->select('id', 'user_id', 'parent_id', 'message as content', 'removed_by', 'edited_by', 'edited', 'created_at', 'updated_at', 'read_by', 'reply_count', 'voice_message', 'file_path', 'file_type', DB::raw("'message' as type"))
            ->with('reactions')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($item) {
                if (isset($item->voice_message) && $item->voice_message) {
                    $item->voice_message = $this->resolveVoiceMessageUrl($item->voice_message);
                }
                return $item;
            });

        $posts = $group->blogs()
            ->select('id', 'user_id', 'title', 'img','file_type',  'content', 'created_at', 'category_id', 'group_id', DB::raw("'post' as type"))
            ->orderBy('created_at', 'asc')
            ->get();

        $elections = $group->group_type !== 'private' ? $group->elections()
            ->select('id', 'starts_at', 'ends_at', 'is_closed', 'created_at', DB::raw("'election' as type"))
            ->orderBy('created_at', 'asc')
            ->get() : collect();

        $polls = $group->polls()
            ->select('id', 'group_id', 'question','expires_at',  'created_at', 'type as real_type', 'main_type', 'created_by', 'skill_id', DB::raw("'poll' as type"))
            ->orderBy('created_at', 'asc')
            ->get();
        
        $anns = Announcement::where('group_level', $group->location_level)
            ->orderBy('created_at', 'asc')
            ->select('*')
            ->addSelect(DB::raw("'ann' as type"))
            ->get();

        $pinnedMessages = PinnedMessage::with(['message', 'pinnedBy'])
            ->where('group_id', $group->id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        

        $combined = $messages->concat($posts)->concat($polls)->concat($anns)
            ->sortBy('created_at');


        
        if($group->location_level == 'section'){
            $group->location_level = 'district';
        }
        $groupSetting = GroupSetting::where('level', $group->location_level)->first();
        
        if($group->specialty_id != null){
            $groupSetting = GroupSetting::where('level', $group->location_level . '_job')->first();
        }elseif($group->experience_id != null){
            $groupSetting = GroupSetting::where('level', $group->location_level . '_experience')->first();
        }elseif($group->age_group_id != null){
            $groupSetting = GroupSetting::where('level', $group->location_level . '_age')->first();
        }elseif($group->gender != null){
            $groupSetting = GroupSetting::where('level', $group->location_level . '_gender')->first();
        }

        if (!$groupSetting) {
            $groupSetting = new GroupSetting([
                'election_status' => 0,
                'max_for_election' => PHP_INT_MAX,
                'election_time' => 0,
                'second_election_time' => 0,
            ]);
        }
                
        $categoryGroupSetting = $groupSetting->id
            ? CategoryGroupSetting::where('group_setting_id', $groupSetting->id)->pluck('category_id')->toArray()
            : [];
        $categories = $categoryGroupSetting
            ? Category::whereIn('id', $categoryGroupSetting)->get()
            : collect();
        
        $activeUserCount = $group->users()->where('role', '>=', '1')->count();
        $allElectionOfGroup = $group->elections()->count();
        
        if($groupSetting && $groupSetting->election_status == 1 && $group->group_type !== 'private' && $activeUserCount >= $groupSetting->max_for_election){
            $election = Election::firstOrCreate([
                'group_id' => $group->id,
                'is_closed' => 0,
            ], [
                'starts_at' => now(),
                'ends_at' => now()->addDays($groupSetting->election_time),
            ]);

            $wasRecentlyCreated = $election->wasRecentlyCreated;

            if($election->ends_at < date('Y-m-d H:i:s')){
                if($election->second_finish_time == null OR $election->second_finish_time < date('Y-m-d H:i:s'))
                $election->second_finish_time = date(
                    'Y-m-d H:i:s', 
                    strtotime('+' . $groupSetting->second_election_time . ' days')
                );
                                $election->ends_at = date(
                    'Y-m-d H:i:s', 
                    strtotime('+' . $groupSetting->second_election_time . ' days')
                );
                $election->save();
                            
            }

            // $electionHideMessage  = \App\Models\Message::create(['group_id' => $group->id, 'user_id' => 171, 'message' => 'پیام پین شده فرم انتخابات']);
            // $pinCreate = \App\Models\PinnedMessage::create(['group_id' => $group->id, 'message_id' => $electionHideMessage->id, 'pinned_by' => 171]);
    
            foreach ($group->users as $user) {
                Candidate::firstOrCreate([
                    'election_id' => $election->id,
                    'user_id' => $user->id,
                ]);
            }

            // Dispatch event for new elections
            if ($wasRecentlyCreated) {
                event(new \App\Events\ElectionStarted($election, $group));
            }
        }else{
            $election = Election::where('group_id', $group->id)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();
            
        }
        if($election != null){
         
        $selectedVotesInspector = Vote::where('position', 0)->where('election_id', $election->id)->where('voter_id', auth()->id())->pluck('candidate_id')->toArray();
            $selectedVotesManager = Vote::where('position', 1)->where('election_id', $election->id)->where('voter_id', auth()->id())->pluck('candidate_id')->toArray();   
        }else{
            
        $selectedVotesInspector = [];
            $selectedVotesManager = [];
        }


        $poll = $group->polls()->latest()->first();

        $userVote = null;

        if ($poll) {
            $userVote = $poll->votes()->where('user_id', auth()->id())->first();
        }

        $specialities = ExperienceField::where('status', 1)->get();

        $group2 = $group;
        
        
            $allManagers = GroupUser::where('group_id', $group2->id)->where('role', 3)->get()->pluck('user_id');

        // Get pending chat requests
        $chatRequests = ChatRequest::whereIn('receiver_id', $allManagers)
            ->where('status', 'pending')
            ->with('sender')
            ->latest()
            ->get();
        
        // Build vote counts safely; if there's no active election, use empty collections
        // فقط رای‌های کاربرانی که status=1 دارند (عضو فعال) شمرده می‌شوند
        if ($election) {
            $activeMemberIds = \App\Models\GroupUser::where('group_id', $group->id)
                ->where('status', 1)
                ->pluck('user_id')
                ->toArray();
            
            $managerCounts = \DB::table('votes')
                ->select('candidate_id', \DB::raw('COUNT(*) as c'))
                ->where('election_id', $election->id)
                ->where('position', '1')
                ->whereIn('voter_id', $activeMemberIds) // فقط رای‌های اعضای فعال
                ->groupBy('candidate_id')
                ->pluck('c', 'candidate_id');

            $inspectorCounts = \DB::table('votes')
                ->select('candidate_id', \DB::raw('COUNT(*) as c'))
                ->where('election_id', $election->id)
                ->where('position', '0')
                ->whereIn('voter_id', $activeMemberIds) // فقط رای‌های اعضای فعال
                ->groupBy('candidate_id')
                ->pluck('c', 'candidate_id');
        } else {
            $managerCounts = collect();
            $inspectorCounts = collect();
        }
    
      // منبع واحد: همهٔ اعضا + تعداد رأی‌ها (حتی اگر صفر)
    $allOptions = $group->users->map(function ($u) use ($managerCounts, $inspectorCounts) {
            $mgr = (int) (($managerCounts && method_exists($managerCounts, 'get')) ? ($managerCounts->get($u->id) ?? 0) : 0);
            $ins = (int) (($inspectorCounts && method_exists($inspectorCounts, 'get')) ? ($inspectorCounts->get($u->id) ?? 0) : 0);

      return [
          'id'              => (int) $u->id,
          'name'            => trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
          'role'            => $u->pivot->role ?? $u->role, // اگر لازم شد بعداً بر اساس نقش فیلتر کن
          'manager_votes'   => $mgr,
          'inspector_votes' => $ins,
      ];
  });

  // فقط «مرتب‌سازی»—بدون هیچ فیلتری؛ همه نمایش داده می‌شوند
  $managersSorted   = $allOptions->sortByDesc('manager_votes')->values();
  $inspectorsSorted = $allOptions->sortByDesc('inspector_votes')->values();


        return view('groups.chat', [
            'group' => $group,
            'groupSetting' => $groupSetting,
            'yourRole' => $yourRole,
            'combined' => $combined,
            'categories' => $categories,
            'election' => $election,
            'selectedVotesInspector' => $selectedVotesInspector,
            'selectedVotesManager' => $selectedVotesManager,
            'polls' => $group->polls()->with('options', 'votes')->get(),
            'poll' => $poll,
            'userVote' => $userVote,
            'specialities' => $specialities,
            'anns' => $anns,
            'group2' => $group2,
            'pinnedMessages' => $pinnedMessages,
            'chatRequests' => $chatRequests,
            'managerCounts' => $managerCounts,
            'inspectorCounts' => $inspectorCounts,
            'managersSorted' => $managersSorted,
            'inspectorsSorted' => $inspectorsSorted,
            'lastReadMessageId' => $lastReadMessageId
        ]);
    }

    public function chatAPI(Group $group, Request $request){
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(max(20, (int) $request->get('per_page', 50)), 100); // بین 20 تا 100
        $offset = ($page - 1) * $perPage;
        $beforeMessageId = $request->get('before_message_id'); // برای pagination به عقب
        $lastMessageId = $request->get('last_message_id'); // برای دریافت فقط پیام‌های جدید
        
        $messagesQuery = $group->messages()
            ->select('id', 'user_id', 'parent_id', 'message as content', 'removed_by', 'edited_by', 'edited', 'created_at', 'updated_at', 'read_by', 'reply_count', 'voice_message', 'file_path', 'file_type', DB::raw("'message' as type"))
            ->with('reactions')
            ->orderBy('created_at', 'desc');
        
        // اگر last_message_id داده شده، فقط پیام‌های جدیدتر از آن را بگیر (برای polling)
        if ($lastMessageId) {
            $messagesQuery->where('id', '>', $lastMessageId);
            $messages = $messagesQuery->orderBy('created_at', 'asc')->get()->values();
            
            // برای polling، فقط پیام‌ها را برمی‌گردانیم (نه posts, polls و ...)
            $posts = collect();
            $polls = collect();
            $elections = collect();
            $anns = collect();
            
            // فقط messages را در combined قرار بده
            $combined = $messages->values();
        } elseif ($beforeMessageId) {
            // اگر before_message_id داده شده، فقط پیام‌های قبل از آن را بگیر
            $messagesQuery->where('id', '<', $beforeMessageId);
            $messages = $messagesQuery->take($perPage)->get()->reverse()->values();
            
            // برای pagination، posts و polls را هم بگیر
            $posts = collect();
            $polls = collect();
            $elections = collect();
            $anns = collect();
        } else {
            $messages = $messagesQuery->take($perPage)->get()->reverse()->values();
            
            // اگر page=1 است و before_message_id نداریم، پست‌ها و نظرسنجی‌ها را هم بگیر
            $posts = collect();
            $polls = collect();
            $elections = collect();
            $anns = collect();
            
            if ($page === 1) {
                $posts = $group->blogs()
                    ->select('id', 'user_id', 'title', 'img', 'content', 'file_type', 'category_id', 'created_at', 'group_id', DB::raw("'post' as type"))
                    ->orderBy('created_at', 'asc')
                    ->get();
                
                $elections = $group->group_type !== 'private' ? $group->elections()
                    ->select('id', 'starts_at', 'ends_at', 'is_closed', 'created_at', DB::raw("'election' as type"))
                    ->orderBy('created_at', 'asc')
                    ->get() : collect();
                
                $polls = $group->polls()
                    ->select('id', 'group_id', 'question', 'expires_at', 'created_by', 'created_at', 'type as real_type', 'main_type', 'skill_id', DB::raw("'poll' as type"))
                    ->orderBy('created_at', 'asc')
                    ->get();
                
                $anns = Announcement::where('group_level', $group->location_level)
                    ->orderBy('created_at', 'asc')
                    ->select('*')
                    ->addSelect(DB::raw("'ann' as type"))
                    ->get();
            }
            
            // فقط اگر last_message_id نداریم، همه را merge کن
            if (!$lastMessageId) {
                $combined = $messages->merge($posts)->merge($elections)->merge($polls)->merge($anns)->sortBy('created_at')->values();
            } else {
                $combined = $messages->values();
            }
        }
        
        // Check if there are more messages
        $hasMore = false;
        if ($messages->isNotEmpty()) {
            $oldestMessageId = $messages->first()->id;
            $hasMore = $group->messages()->where('id', '<', $oldestMessageId)->exists();
        }
        
        $poll = $group->polls()->latest()->with('options')->first();
        
        $userVote = null;
        if ($poll && auth()->check()) {
            $userVote = $poll->votes()->where('user_id', auth()->id())->first();
        }
        
        $yourRole = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->value('role');
        
        $categories = Category::all();
        $specialities = OccupationalField::where('status', 1)->get();
        
        // بررسی می‌کنیم که آیا درخواست AJAX است یا header Accept: application/json دارد
        $isJsonRequest = $request->wantsJson() || 
                        $request->expectsJson() || 
                        $request->ajax() || 
                        $request->header('Accept') === 'application/json' ||
                        str_contains($request->header('Accept', ''), 'application/json');
        
        if ($isJsonRequest) {
            // اگر last_message_id وجود دارد، فقط messages را برمی‌گردانیم
            // IMPORTANT: بررسی دقیق lastMessageId
            if ($lastMessageId && $lastMessageId !== 'null' && $lastMessageId !== null && $lastMessageId !== '') {
                // فقط messages را map کن (نه posts, polls و ...)
                $combined = $combined->filter(function($item) {
                    return $item->type === 'message';
                })->map(function($item) use ($group) {
                    // Get sender information
                    $sender = $group->users->firstWhere('id', $item->user_id);
                    $senderName = $sender ? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) : 'کاربر';
                    $item->sender = $senderName;
                    
                    // Handle parent message (reply) information
                    if ($item->parent_id) {
                        $parent = \App\Models\Message::with('user')->find($item->parent_id);
                        if ($parent) {
                            $parentUser = $parent->user;
                            $item->parent_sender = $parentUser ? trim(($parentUser->first_name ?? '') . ' ' . ($parentUser->last_name ?? '')) : 'کاربر';
                            $item->parent_content = strip_tags($parent->message ?? '');
                        }
                    }
                    
                    // Handle voice message URL
                    if (isset($item->voice_message) && $item->voice_message) {
                        $item->voice_message = $this->resolveVoiceMessageUrl($item->voice_message);
                    }
                    
                    // Format created_at for display
                    $item->created_at = $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('H:i') : '';
                    
                    // Add reactions data
                    if (isset($item->reactions) && $item->reactions) {
                        $reactions = $item->reactions->groupBy('reaction_type')
                            ->map(function($group) {
                                return [
                                    'type' => $group->first()->reaction_type,
                                    'count' => $group->count()
                                ];
                            })
                            ->values();
                        $item->reactions = $reactions;
                    } else {
                        $item->reactions = [];
                    }
                    
                    // Rename content to message for consistency
                    $item->message = $item->content;
                    
                    return $item;
                })->values();
            } else {
                // برای درخواست‌های عادی (بدون last_message_id)
                $combined = $combined->map(function($item) use ($group) {
                    if ($item->type === 'message') {
                        // Get sender information
                        $sender = $group->users->firstWhere('id', $item->user_id);
                        $senderName = $sender ? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) : 'کاربر';
                        $item->sender = $senderName;
                        
                        // Handle parent message (reply) information
                        if ($item->parent_id) {
                            $parent = \App\Models\Message::with('user')->find($item->parent_id);
                            if ($parent) {
                                $parentUser = $parent->user;
                                $item->parent_sender = $parentUser ? trim(($parentUser->first_name ?? '') . ' ' . ($parentUser->last_name ?? '')) : 'کاربر';
                                $item->parent_content = strip_tags($parent->message ?? '');
                            }
                        }
                        
                        // Handle voice message URL
                        if (isset($item->voice_message) && $item->voice_message) {
                            $item->voice_message = $this->resolveVoiceMessageUrl($item->voice_message);
                        }
                        
                        // Format created_at for display
                        $item->created_at = $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('H:i') : '';
                        
                        // Add reactions data
                        if (isset($item->reactions) && $item->reactions) {
                            $reactions = $item->reactions->groupBy('reaction_type')
                                ->map(function($group) {
                                    return [
                                        'type' => $group->first()->reaction_type,
                                        'count' => $group->count()
                                    ];
                                })
                                ->values();
                            $item->reactions = $reactions;
                        } else {
                            $item->reactions = [];
                        }
                        
                        // Rename content to message for consistency
                        $item->message = $item->content;
                    }
                    
                    return $item;
                })->values();
            }
            
            // Get the latest message ID for polling
            $latestMessageId = $messages->isNotEmpty() ? $messages->last()->id : null;
            
            return response()->json([
                'messages' => $combined,
                'has_more' => $hasMore,
                'page' => $page,
                'next_page' => $hasMore ? $page + 1 : null,
                'oldest_message_id' => $messages->isNotEmpty() ? $messages->first()->id : null,
                'latest_message_id' => $latestMessageId
            ]);
        }
        
        return view('partials.messages', compact('combined', 'group', 'userVote', 'yourRole', 'categories', 'specialities'))->render();
    }

    private function resolveVoiceMessageUrl(?string $voiceMessage): ?string
    {
        if (!$voiceMessage) {
            return null;
        }

        if (str_starts_with($voiceMessage, 'http://') || str_starts_with($voiceMessage, 'https://')) {
            return $voiceMessage;
        }

        $path = ltrim($voiceMessage, '/');
        if (!Storage::disk('public')->exists($path)) {
            return null;
        }

        $pathParts = explode('/', $path);
        $encodedParts = array_map('rawurlencode', $pathParts);
        return asset('storage/' . implode('/', $encodedParts));
    }

    public function delegation(Poll $poll, User $expert){
        $delegation = Delegation::where('poll_id', $poll->id)->where('user_id', auth()->user()->id)->first();
        if($delegation != null){
            $delegation->delete();
            return back()->with('success', 'تفویض با موفقیت حذف شد');
        }else{
            Delegation::create([
                'poll_id' => $poll->id,
                'user_id' => auth()->user()->id,
                'expert_id' => $expert->id
            ]);
            return back()->with('success', 'تفویض با موفقیت انجام شد');
        }
    }

    public function clearHistory(Group $group)
    {
        if ($group->group_type !== 'private') {
            return response()->json([
                'success' => false,
                'message' => 'این قابلیت فقط برای چت‌های خصوصی در دسترس است'
            ], 403);
        }

        $group->messages()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'تاریخچه چت با موفقیت پاک شد'
        ]);
    }

    public function deleteChat(Group $group)
    {
        if ($group->group_type !== 'private') {
            return response()->json([
                'success' => false,
                'message' => 'این قابلیت فقط برای چت‌های خصوصی در دسترس است'
            ], 403);
        }

        $group->messages()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'چت با موفقیت حذف شد'
        ]);
    }

    public function reportUser(Request $request, Group $group)
    {
        if ($group->group_type !== 'private') {
            return response()->json([
                'success' => false,
                'message' => 'این قابلیت فقط برای چت‌های خصوصی در دسترس است'
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string',
            'description' => 'required|string'
        ]);

        $report = ReportedMessage::create([
            'group_id' => $group->id,
            'user_id' => auth()->user()->id,
            'reason' => $request->reason,
            'description' => $request->description,
            'reported_by' => auth()->user()->id,
            'status' => 'pending',
            'escalated_to_admin' => false,
        ]);

        // Dispatch event for notifying managers and inspectors
        event(new \App\Events\MessageReported($report, $group, auth()->user()));
        
        return response()->json([
            'success' => true,
            'message' => 'گزارش با موفقیت ارسال شد'
        ]);
    }
}
