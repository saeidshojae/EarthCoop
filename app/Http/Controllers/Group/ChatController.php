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
use App\Models\PollVote;
use App\Models\User;
use App\Models\Vote;
use App\Models\PinnedMessage;
use App\Models\ReportedMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    public function chat(Group $group)
    {
        $t0 = microtime(true);
        // Keep initial page load lightweight; older history is fetched via API pagination.
        $initialMessageLimit = 50;   // کاهش از 120 به 50 برای سرعت بیشتر
        $initialPostLimit = 20;      // کاهش از 40 به 20
        $initialPollLimit = 20;      // کاهش از 40 به 20

        $groupUser = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$groupUser || (int) $groupUser->status !== 1) {
            abort(403, 'Unauthorized');
        }
        
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
        \Log::info('ChatController@chat T1 (after groupUser): ' . round((microtime(true)-$t0)*1000) . 'ms');

        $messages = $group->messages()
            ->select('id', 'user_id', 'parent_id', 'message as content', 'removed_by', 'edited_by', 'edited', 'created_at', 'updated_at', 'read_by', 'reply_count', 'voice_message', 'file_path', 'file_type', DB::raw("'message' as type"))
            ->with(['reactions', 'user:id,first_name,last_name,avatar'])
            ->orderBy('id', 'desc')
            ->limit($initialMessageLimit)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($item) {
                if (isset($item->voice_message) && $item->voice_message) {
                    $item->voice_message_url = $this->resolveVoiceMessageUrl($item->id, $item->voice_message);
                }
                return $item;
            });

        $posts = $group->blogs()
            ->select('id', 'user_id', 'title', 'img','file_type',  'content', 'created_at', 'category_id', 'group_id', 'read_by', DB::raw("'post' as type"))
            ->with(['user:id,first_name,last_name,avatar', 'reactions'])
            ->withCount('comments')
            ->orderBy('id', 'desc')
            ->limit($initialPostLimit)
            ->get();
        $posts = $posts->reverse()->values();

        // Preload GroupUser records for post authors با یک کوئری
        $postUserIds = $posts->pluck('user_id')->unique()->filter()->values();
        $postGroupUsersMap = $postUserIds->isNotEmpty()
            ? \App\Models\GroupUser::where('group_id', $group->id)
                ->whereIn('user_id', $postUserIds)
                ->get()
                ->keyBy('user_id')
            : collect();

        $elections = $group->group_type !== 'private' ? $group->elections()
            ->select('id', 'starts_at', 'ends_at', 'is_closed', 'created_at', DB::raw("'election' as type"))
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get() : collect();
        $elections = $elections->reverse()->values();

        $polls = $group->polls()
            ->select('id', 'group_id', 'question','expires_at',  'created_at', 'type as real_type', 'main_type', 'created_by', 'skill_id', DB::raw("'poll' as type"))
            ->with([
                'user:id,first_name,last_name,avatar',
                'skill:id,name',
                'options:id,poll_id,text',
            ])
            ->orderBy('id', 'desc')
            ->limit($initialPollLimit)
            ->get();
        $polls = $polls->reverse()->values();
        \Log::info('ChatController@chat T2 (after messages+posts+polls): ' . round((microtime(true)-$t0)*1000) . 'ms');

        $pollIds = $polls->pluck('id')->filter()->values();
        // از subquery به جای لود همه ID‌ها در حافظه استفاده می‌کنیم
        // (جلوگیری از WHERE IN با هزاران مقدار که باعث timeout می‌شود)
        $activeMemberIdsSubquery = function($q) use ($group) {
            $q->select('user_id')
              ->from('group_user')
              ->where('group_id', $group->id)
              ->where('status', 1);
        };

        $pollOptionVotes = [];
        $pollTotals = [];
        $userVotesByPollId = [];
        $delegationsByPollId = collect();

        if ($pollIds->isNotEmpty()) {
            $voteAgg = PollVote::query()
                ->select('poll_id', 'option_id', DB::raw('COUNT(*) as c'))
                ->whereIn('poll_id', $pollIds)
                ->whereIn('user_id', $activeMemberIdsSubquery)
                ->groupBy('poll_id', 'option_id')
                ->get();

            foreach ($voteAgg as $row) {
                $pid = (int) $row->poll_id;
                $oid = (int) $row->option_id;
                $count = (int) $row->c;
                if (!isset($pollOptionVotes[$pid])) {
                    $pollOptionVotes[$pid] = [];
                }
                $pollOptionVotes[$pid][$oid] = $count;
                $pollTotals[$pid] = ($pollTotals[$pid] ?? 0) + $count;
            }

            $userVotesByPollId = PollVote::query()
                ->whereIn('poll_id', $pollIds)
                ->where('user_id', auth()->id())
                ->pluck('option_id', 'poll_id')
                ->map(fn ($v) => (int) $v)
                ->toArray();

            $delegationsByPollId = Delegation::query()
                ->whereIn('poll_id', $pollIds)
                ->where('user_id', auth()->id())
                ->get()
                ->keyBy('poll_id');

            $specializedPollIds = $polls
                ->filter(fn ($p) => (int) ($p->real_type ?? 0) === 1)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($specializedPollIds->isNotEmpty()) {
                $delegationTotals = Delegation::query()
                    ->select('poll_id', DB::raw('COUNT(*) as c'))
                    ->whereIn('poll_id', $specializedPollIds)
                    ->groupBy('poll_id')
                    ->pluck('c', 'poll_id');

                foreach ($delegationTotals as $pid => $c) {
                    $pid = (int) $pid;
                    $pollTotals[$pid] = ($pollTotals[$pid] ?? 0) + (int) $c;
                }
            }
        }
        
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
        \Log::info('ChatController@chat T3 (after polls+anns+combined): ' . round((microtime(true)-$t0)*1000) . 'ms');


        
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
            : Category::all();
        
        $activeUserCount = $group->users()->where('role', '>=', '1')->count();
        $allElectionOfGroup = $group->elections()->count();
        \Log::info('ChatController@chat T4 (after groupSetting+election count): ' . round((microtime(true)-$t0)*1000) . 'ms');
        
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
    
            // Candidate syncing is expensive for large groups; run only once when election is created.
            if ($wasRecentlyCreated) {
                $groupUsers = $group->users()->select('users.id')->get();
                foreach ($groupUsers as $user) {
                    Candidate::firstOrCreate([
                        'election_id' => $election->id,
                        'user_id' => $user->id,
                    ]);
                }
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
        \Log::info('ChatController@chat T5 (after election queries): ' . round((microtime(true)-$t0)*1000) . 'ms');

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
            // استفاده از subquery به جای pluck برای جلوگیری از timeout
            $electionActiveMembersSubquery = function($q) use ($group) {
                $q->select('user_id')
                  ->from('group_user')
                  ->where('group_id', $group->id)
                  ->where('status', 1);
            };

            $managerCounts = \DB::table('votes')
                ->select('candidate_id', \DB::raw('COUNT(*) as c'))
                ->where('election_id', $election->id)
                ->where('position', '1')
                ->whereIn('voter_id', $electionActiveMembersSubquery) // فقط رای‌های اعضای فعال
                ->groupBy('candidate_id')
                ->pluck('c', 'candidate_id');

            $inspectorCounts = \DB::table('votes')
                ->select('candidate_id', \DB::raw('COUNT(*) as c'))
                ->where('election_id', $election->id)
                ->where('position', '0')
                ->whereIn('voter_id', $electionActiveMembersSubquery) // فقط رای‌های اعضای فعال
                ->groupBy('candidate_id')
                ->pluck('c', 'candidate_id');
        } else {
            $managerCounts = collect();
            $inspectorCounts = collect();
        }
    
      // منبع واحد: همهٔ اعضا + تعداد رأی‌ها (حتی اگر صفر)
      // فقط وقتی انتخابات فعال است این کوئری اجرا می‌شود
        if ($election) {
            $groupUsersForElection = $group->users()->select('users.id', 'users.first_name', 'users.last_name')->get();
            $allOptions = $groupUsersForElection->map(function ($u) use ($managerCounts, $inspectorCounts) {
                    $mgr = (int) (($managerCounts && method_exists($managerCounts, 'get')) ? ($managerCounts->get($u->id) ?? 0) : 0);
                    $ins = (int) (($inspectorCounts && method_exists($inspectorCounts, 'get')) ? ($inspectorCounts->get($u->id) ?? 0) : 0);
              return [
                  'id'              => (int) $u->id,
                  'name'            => trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                  'role'            => $u->pivot->role ?? $u->role,
                  'manager_votes'   => $mgr,
                  'inspector_votes' => $ins,
              ];
          });
            $managersSorted   = $allOptions->sortByDesc('manager_votes')->values();
            $inspectorsSorted = $allOptions->sortByDesc('inspector_votes')->values();
        } else {
            $managersSorted   = collect();
            $inspectorsSorted = collect();
        }
        \Log::info('ChatController@chat T6 (after managersSorted): ' . round((microtime(true)-$t0)*1000) . 'ms');

        return view('groups.chat', [
            'group' => $group,
            'groupSetting' => $groupSetting,
            'yourRole' => $yourRole,
            'combined' => $combined,
            'categories' => $categories,
            'election' => $election,
            'selectedVotesInspector' => $selectedVotesInspector,
            'selectedVotesManager' => $selectedVotesManager,
            'polls' => $group->polls()->with('options')->latest('id')->limit($initialPollLimit)->get(),
            'pollTotals' => $pollTotals,
            'pollOptionVotes' => $pollOptionVotes,
            'userVotesByPollId' => $userVotesByPollId,
            'delegationsByPollId' => $delegationsByPollId,
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
            'lastReadMessageId' => $lastReadMessageId,
            'postGroupUsersMap' => $postGroupUsersMap
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
        
        
        // بررسی می‌کنیم که آیا درخواست AJAX است یا header Accept: application/json دارد
        $isJsonRequest = $request->wantsJson() ||
            $request->expectsJson() ||
            $request->ajax() ||
            $request->header('Accept') === 'application/json' ||
            str_contains($request->header('Accept', ''), 'application/json');

        if ($isJsonRequest) {
            $formatMessageItems = function ($items) {
                $messagesOnly = $items->filter(fn ($item) => $item->type === 'message')->values();
                if ($messagesOnly->isEmpty()) {
                    return $items->values();
                }

                $userIds = $messagesOnly->pluck('user_id')->filter()->unique()->values();
                $parentIds = $messagesOnly->pluck('parent_id')->filter()->unique()->values();

                $usersById = User::query()
                    ->whereIn('id', $userIds)
                    ->select('id', 'first_name', 'last_name')
                    ->get()
                    ->keyBy('id');

                $parentsById = \App\Models\Message::query()
                    ->with('user:id,first_name,last_name')
                    ->whereIn('id', $parentIds)
                    ->get()
                    ->keyBy('id');

                return $items->map(function ($item) use ($usersById, $parentsById) {
                    if ($item->type !== 'message') {
                        return $item;
                    }

                    $sender = $usersById->get($item->user_id);
                    $item->sender = $sender
                        ? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? ''))
                        : 'User';

                    if ($item->parent_id) {
                        $parent = $parentsById->get($item->parent_id);
                        if ($parent) {
                            $parentUser = $parent->user;
                            $item->parent_sender = $parentUser
                                ? trim(($parentUser->first_name ?? '') . ' ' . ($parentUser->last_name ?? ''))
                                : 'User';
                            $item->parent_content = strip_tags($parent->message ?? '');
                        }
                    }

                    if (!empty($item->voice_message)) {
                        $item->voice_message_url = $this->resolveVoiceMessageUrl($item->id, $item->voice_message);
                    }

                    $item->created_at = $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('H:i') : '';
                    $item->message = $item->content;

                    if (isset($item->reactions) && $item->reactions) {
                        $item->reactions = $item->reactions
                            ->groupBy('reaction_type')
                            ->map(function ($group) {
                                return [
                                    'type' => $group->first()->reaction_type,
                                    'count' => $group->count(),
                                ];
                            })
                            ->values();
                    } else {
                        $item->reactions = [];
                    }

                    return $item;
                })->values();
            };

            if (!empty($lastMessageId) && $lastMessageId !== 'null') {
                $messagesPayload = $formatMessageItems($messages);
                $latestMessageId = $messagesPayload->isNotEmpty() ? $messagesPayload->last()->id : (int) $lastMessageId;

                // پیام‌های تازه ویرایش‌شده (برای کاربرانی که پیام رو قبلاً دریافت کرده‌اند)
                $updatedMsgs = $group->messages()
                    ->select('id', 'user_id', 'message as content', 'edited', 'updated_at', DB::raw("'message' as type"))
                    ->where('id', '<=', (int) $lastMessageId)
                    ->where('edited', true)
                    ->where('updated_at', '>=', now()->subSeconds(90))
                    ->get()
                    ->map(fn ($m) => ['id' => $m->id, 'message' => $m->content, 'edited' => true])
                    ->values();

                // شناسه‌های پیام‌های حذف‌شده (از cache)
                $deletedIds = Cache::get('group.' . $group->id . '.deleted_ids', []);

                return response()->json([
                    'messages' => $messagesPayload,
                    'updated_messages' => $updatedMsgs,
                    'deleted_message_ids' => array_values($deletedIds),
                    'has_more' => false,
                    'page' => 1,
                    'next_page' => null,
                    'oldest_message_id' => $messagesPayload->isNotEmpty() ? $messagesPayload->first()->id : null,
                    'latest_message_id' => $latestMessageId
                ]);
            }

            $combined = $formatMessageItems($combined);
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

        return view('partials.messages', compact('combined', 'group', 'userVote', 'yourRole', 'categories', 'specialities'))->render();
    }

    public function postsFeed(Group $group, Request $request)
    {
        $isMember = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->where('status', 1)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied.',
            ], 403);
        }

        $afterId = (int) $request->query('after_id', 0);
        $limit = min(max((int) $request->query('limit', 10), 1), 50);

        $query = $group->blogs()
            ->with(['user', 'category', 'comments', 'reactions'])
            ->orderBy('id', 'asc');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        } else {
            $query->latest('id')->take($limit);
        }

        $posts = $query->get();
        if ($afterId === 0) {
            $posts = $posts->sortBy('id')->values();
        }

        $categories = Category::all();
        $payload = $posts->map(function ($post) use ($group, $categories) {
            return [
                'id' => (int) $post->id,
                'html' => view('groups.partials.post', [
                    'item' => $post,
                    'group' => $group,
                    'userVote' => null,
                    'categories' => $categories,
                ])->render(),
            ];
        })->values();

        // Fetch cached deleted post IDs for this group
        $deletedPostIds = array_values(Cache::get('group.' . $group->id . '.deleted_post_ids', []));

        // Fetch posts edited OR reacted to in the last 90 seconds.
        // Reactions also call $blog->touch() so updated_at is updated on every reaction change.
        // Only meaningful when client has already done an initial load (afterId > 0)
        $updatedPosts = [];
        if ($afterId > 0) {
            $updatedPosts = $group->blogs()
                ->with(['user', 'category', 'comments', 'reactions'])
                ->where('id', '<=', $afterId)
                ->where('updated_at', '>=', now()->subSeconds(90))
                ->whereColumn('updated_at', '!=', 'created_at')
                ->get()
                ->map(function ($post) use ($group, $categories) {
                    return [
                        'id' => (int) $post->id,
                        'html' => view('groups.partials.post', [
                            'item' => $post,
                            'group' => $group,
                            'userVote' => null,
                            'categories' => $categories,
                        ])->render(),
                    ];
                })->values()->all();
        }

        return response()->json([
            'status' => 'success',
            'posts' => $payload,
            'latest_post_id' => (int) ($posts->last()->id ?? $afterId),
            'deleted_post_ids' => $deletedPostIds,
            'updated_posts' => $updatedPosts,
        ]);
    }

    public function postsReconcile(Group $group, Request $request)
    {
        $isMember = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->where('status', 1)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied.',
            ], 403);
        }

        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->take(250);

        if ($ids->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'deleted_ids' => [],
            ]);
        }

        $existingIds = $group->blogs()
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $deletedIds = array_values(array_diff($ids->all(), $existingIds));

        return response()->json([
            'status' => 'success',
            'deleted_ids' => $deletedIds,
        ]);
    }

    public function messagesReconcile(Group $group, Request $request)
    {
        $isMember = GroupUser::where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->where('status', 1)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied.',
            ], 403);
        }

        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->take(500);

        if ($ids->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'deleted_ids' => [],
            ]);
        }

        $existingIds = $group->messages()
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $deletedIds = array_values(array_diff($ids->all(), $existingIds));

        return response()->json([
            'status' => 'success',
            'deleted_ids' => $deletedIds,
        ]);
    }

    private function resolveVoiceMessageUrl(?int $messageId, ?string $voiceMessage): ?string
    {
        if (!$voiceMessage) {
            return null;
        }

        if ($messageId && $messageId > 0) {
            return route('groups.messages.voice', ['message' => $messageId]);
        }

        if (str_starts_with($voiceMessage, 'http://') || str_starts_with($voiceMessage, 'https://')) {
            return $voiceMessage;
        }

        $path = ltrim($voiceMessage, '/');
        $pathParts = explode('/', $path);
        $encodedParts = array_map('rawurlencode', $pathParts);
        if (str_starts_with($path, 'storage/')) {
            return '/' . implode('/', $encodedParts);
        }
        return '/storage/' . implode('/', $encodedParts);
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
