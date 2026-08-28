<?php

namespace App\Http\Controllers\Group;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\CategoryGroupSetting;
use App\Models\ChatRequest;
use App\Models\Delegation;
use App\Models\Election;
use App\Models\ExperienceField;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupSession;
use App\Models\GroupSyncEvent;
use App\Models\GroupUser;
use App\Models\PinnedMessage;
use App\Models\PollVote;
use App\Models\User;
use App\Models\Vote;
use App\Services\Elections\ElectionPolicyResolver;
use App\Services\GroupChat\GroupFeedService;
use App\Services\GroupChat\GroupSessionService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Read-only presenter for the group chat page.
 *
 * Election lifecycle creation/closing/tallying is intentionally absent here.
 * The systemic scheduler/domain services are the only owners of those writes.
 */
class SystemicElectionChatController extends Controller
{
    public function chat(
        Group $group,
        GroupSessionService $sessionService,
        ElectionPolicyResolver $policyResolver,
    ) {
        $this->authorize('view', $group);
        $sessionService->activateDueForGroup($group);
        $group->refresh();

        $initialMessageLimit = 50;
        $initialPostLimit = 20;
        $initialPollLimit = 20;
        [$focusedPinType, $focusedPinId] = array_pad(explode(':', (string) request()->query('pin'), 2), 2, null);
        $focusedPinId = ctype_digit((string) $focusedPinId) ? (int) $focusedPinId : null;

        $groupUser = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->first();
        if (! $groupUser || (int) $groupUser->status !== 1) {
            abort(403, 'Unauthorized');
        }

        $pivotRole = (int) $groupUser->role;
        if (in_array($pivotRole, [2, 3, 4, 5], true)) {
            $yourRole = $pivotRole;
        } else {
            $level = strtolower(trim((string) ($group->getRawOriginal('location_level') ?? $group->location_level ?? '')));
            $yourRole = in_array($level, ['neighborhood', 'street', 'alley'], true) ? 1 : 0;
        }

        $lastReadMessageId = $groupUser->last_read_message_id;
        $unreadContentCounts = $this->countUnreadContent($group, (int) auth()->id());

        $messages = $group->messages()
            ->visibleInChat()
            ->select('id', 'user_id', 'parent_id', 'message as content', 'removed_by', 'edited_by', 'edited', 'created_at', 'updated_at', 'read_by', 'reply_count', 'voice_message', 'file_path', 'file_type', DB::raw("'message' as type"))
            ->with(['reactions', 'user:id,first_name,last_name,avatar'])
            ->when($focusedPinType === 'message' && $focusedPinId, fn ($query) => $query->orderByRaw('id = ? DESC', [$focusedPinId]))
            ->orderByDesc('id')->limit($initialMessageLimit)->get()->reverse()->values()
            ->map(function ($item) {
                if (! empty($item->voice_message)) {
                    $item->voice_message_url = route('groups.messages.voice', ['message' => $item->id]);
                }
                return $item;
            });

        $posts = $group->blogs()
            ->select('id', 'user_id', 'title', 'img', 'file_type', 'content', 'created_at', 'category_id', 'group_id', 'read_by', DB::raw("'post' as type"))
            ->with(['user:id,first_name,last_name,avatar', 'reactions'])
            ->withCount('comments')
            ->when($focusedPinType === 'post' && $focusedPinId, fn ($query) => $query->orderByRaw('id = ? DESC', [$focusedPinId]))
            ->orderByDesc('id')->limit($initialPostLimit)->get()->reverse()->values();

        $postUserIds = $posts->pluck('user_id')->unique()->filter()->values();
        $postGroupUsersMap = $postUserIds->isNotEmpty()
            ? GroupUser::query()->where('group_id', $group->id)->whereIn('user_id', $postUserIds)->get()->keyBy('user_id')
            : collect();

        $polls = $group->polls()
            ->select('id', 'group_id', 'question', 'expires_at', 'created_at', 'type as real_type', 'main_type', 'created_by', 'skill_id', 'read_by', DB::raw("'poll' as type"))
            ->with(['user:id,first_name,last_name,avatar', 'skill:id,name', 'options:id,poll_id,text'])
            ->when($focusedPinType === 'poll' && $focusedPinId, fn ($query) => $query->orderByRaw('id = ? DESC', [$focusedPinId]))
            ->orderByDesc('id')->limit($initialPollLimit)->get()->reverse()->values();

        $pollIds = $polls->pluck('id')->filter()->values();
        $pollOptionVotes = [];
        $pollTotals = [];
        $userVotesByPollId = [];
        $delegationsByPollId = collect();

        if ($pollIds->isNotEmpty()) {
            $activeMemberIdsSubquery = function ($query) use ($group) {
                $query->select('user_id')->from('group_user')->where('group_id', $group->id)->where('status', 1);
            };
            $voteAgg = PollVote::query()
                ->select('poll_id', 'option_id', DB::raw('COUNT(*) as c'))
                ->whereIn('poll_id', $pollIds)
                ->whereIn('user_id', $activeMemberIdsSubquery)
                ->groupBy('poll_id', 'option_id')->get();
            foreach ($voteAgg as $row) {
                $pid = (int) $row->poll_id;
                $oid = (int) $row->option_id;
                $pollOptionVotes[$pid][$oid] = (int) $row->c;
                $pollTotals[$pid] = ($pollTotals[$pid] ?? 0) + (int) $row->c;
            }
            $userVotesByPollId = PollVote::query()
                ->whereIn('poll_id', $pollIds)->where('user_id', auth()->id())
                ->pluck('option_id', 'poll_id')->map(fn ($v) => (int) $v)->toArray();
            $delegationsByPollId = Delegation::query()
                ->whereIn('poll_id', $pollIds)->where('user_id', auth()->id())->get()->keyBy('poll_id');

            // Preserve the legacy group-chat display contract for specialized
            // polls: delegated participation contributes to the displayed total.
            $specializedPollIds = $polls
                ->filter(fn ($poll) => (int) ($poll->real_type ?? 0) === 1)
                ->pluck('id')->map(fn ($id) => (int) $id)->values();
            if ($specializedPollIds->isNotEmpty()) {
                $delegationTotals = Delegation::query()
                    ->select('poll_id', DB::raw('COUNT(*) as c'))
                    ->whereIn('poll_id', $specializedPollIds)
                    ->groupBy('poll_id')
                    ->pluck('c', 'poll_id');
                foreach ($delegationTotals as $pollId => $count) {
                    $pid = (int) $pollId;
                    $pollTotals[$pid] = ($pollTotals[$pid] ?? 0) + (int) $count;
                }
            }
        }

        $anns = Announcement::query()->where('group_level', $group->location_level)
            ->orderBy('created_at')->select('*')->addSelect(DB::raw("'ann' as type"))->get();
        $sessions = GroupSession::query()->where('group_id', $group->id)
            ->whereIn('status', ['active', 'ended'])->whereNotNull('started_at')
            ->latest('started_at')->limit(20)->get()->flatMap(function ($session) {
                $started = clone $session;
                $started->type = 'session';
                $started->event_status = 'active';
                $started->event_at = $session->started_at;
                $started->created_at = $session->started_at;
                if (! $session->ended_at) return [$started];
                $ended = clone $session;
                $ended->type = 'session';
                $ended->event_status = 'ended';
                $ended->event_at = $session->ended_at;
                $ended->created_at = $session->ended_at;
                return [$started, $ended];
            });
        $combined = $messages->concat($posts)->concat($polls)->concat($anns)->concat($sessions)->sortBy('created_at');
        $pinnedMessages = PinnedMessage::with(['message', 'pinnedBy'])
            ->where('group_id', $group->id)->orderByDesc('created_at')->get();

        // GroupSetting remains only a compatibility source for non-election category configuration.
        // Election UI itself uses the frozen policy attached to the open cycle.
        try {
            $groupSetting = $policyResolver->resolveForGroup($group);
        } catch (RuntimeException) {
            $groupSetting = new GroupSetting(['election_status' => 0, 'max_for_election' => PHP_INT_MAX]);
        }
        $categoryGroupSetting = $groupSetting->id
            ? CategoryGroupSetting::query()->where('group_setting_id', $groupSetting->id)->pluck('category_id')->toArray()
            : [];
        $categories = $categoryGroupSetting
            ? Category::query()->whereIn('id', $categoryGroupSetting)->get()
            : Category::all();

        // Canonical election read path: never create, extend, close, tally or sync candidates here.
        $election = Election::query()->with('policyVersion')
            ->where('group_id', $group->id)
            ->where('lifecycle_status', ElectionLifecycleStatus::Open->value)
            ->orderByDesc('cycle_number')->orderByDesc('id')->first();
        $latestElection = Election::query()->with('policyVersion')
            ->where('group_id', $group->id)
            ->orderByDesc('cycle_number')->orderByDesc('id')->first();
        $electionPolicy = $election?->policyVersion;

        $currentVotes = $election
            ? Vote::query()->where('election_id', $election->id)->where('voter_id', auth()->id())->get()
            : collect();
        $selectedVotesInspector = $currentVotes->filter(fn ($vote) => in_array((string) $vote->position, ['0', 'inspector'], true))
            ->pluck('candidate_user_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $selectedVotesManager = $currentVotes->filter(fn ($vote) => in_array((string) $vote->position, ['1', 'manager'], true))
            ->pluck('candidate_user_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $voteVisibilityByCandidate = $currentVotes->filter(fn ($vote) => $vote->candidate_user_id !== null)
            ->mapWithKeys(fn ($vote) => [(int) $vote->candidate_user_id => (string) ($vote->vote_visibility?->value ?? $vote->vote_visibility ?? 'confidential')])
            ->all();

        $electionMembers = User::query()
            ->join('group_user', 'group_user.user_id', '=', 'users.id')
            ->where('group_user.group_id', $group->id)
            ->where('group_user.status', 1)
            ->where('group_user.role', '>=', 1)
            ->where('group_user.role', '!=', 4)
            ->where('users.is_system', false)
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.avatar', 'group_user.role as membership_role')
            ->orderBy('users.first_name')->orderBy('users.last_name')->orderBy('users.id')->get();

        $poll = $group->polls()->latest()->first();
        $userVote = $poll ? $poll->votes()->where('user_id', auth()->id())->first() : null;
        $specialities = ExperienceField::query()->where('status', 1)->get();
        $group2 = $group;
        $allManagers = GroupUser::query()->where('group_id', $group->id)->where('role', 3)->pluck('user_id');
        $chatRequests = ChatRequest::query()->whereIn('receiver_id', $allManagers)
            ->where('request_to_group', $group->id)->where('status', 'pending')->with('sender')->latest()->get();

        return view('groups.chat', [
            'group' => $group,
            'groupSetting' => $groupSetting,
            'yourRole' => $yourRole,
            'combined' => $combined,
            'categories' => $categories,
            'election' => $election,
            'latestElection' => $latestElection,
            'electionPolicy' => $electionPolicy,
            'electionMembers' => $electionMembers,
            'voteVisibilityByCandidate' => $voteVisibilityByCandidate,
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
            // Legacy modal variables are intentionally privacy-safe/empty; the canonical modal does not use raw vote rankings.
            'managerCounts' => collect(),
            'inspectorCounts' => collect(),
            'managersSorted' => $electionMembers,
            'inspectorsSorted' => $electionMembers,
            'lastReadMessageId' => $lastReadMessageId,
            'unreadContentCounts' => $unreadContentCounts,
            'postGroupUsersMap' => $postGroupUsersMap,
            'groupSyncCursor' => \Illuminate\Support\Facades\Schema::hasTable('group_sync_events')
                ? (int) GroupSyncEvent::query()->where('group_id', $group->id)->max('id') : 0,
            'realtimeConfig' => app(\App\Services\GroupChat\RealtimeSettingsService::class)->publicConfig(),
            'pendingSessionParticipationCount' => in_array((int) $yourRole, [2, 3], true)
                ? \App\Models\GroupSessionParticipationRequest::query()->where('group_id', $group->id)->where('status', 'pending')->count() : 0,
        ]);
    }

    private function countUnreadContent(Group $group, int $userId): array
    {
        $feed = app(GroupFeedService::class);
        if (config('group-chat.features.feed_unread_v1', true) && $feed->available()) {
            return $feed->unreadCounts((int) $group->id, $userId);
        }

        $countUnread = static function ($query, string $authorColumn) use ($userId): int {
            return $query->where($authorColumn, '!=', $userId)
                ->where(function ($query) use ($userId) {
                    $query->whereNull('read_by')->orWhereJsonDoesntContainKey('read_by->'.$userId);
                })->count();
        };

        $messages = $countUnread($group->messages()->visibleInChat(), 'user_id');
        $posts = $countUnread($group->blogs(), 'user_id');
        $polls = $countUnread($group->polls(), 'created_by');
        return ['total' => $messages + $posts + $polls, 'messages' => $messages, 'posts' => $posts, 'polls' => $polls];
    }
}
