<?php

namespace App\Http\Controllers\API\V1;

use App\Enums\Elections\ElectionBallotCommentVisibility;
use App\Enums\Elections\ElectionLifecycleStatus;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Vote;
use App\Services\Elections\ElectionBallotService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ElectionController extends Controller
{
    public function current(Request $request, Group $group)
    {
        $membership = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 1)
            ->where('role', '!=', 4)
            ->first();

        abort_unless($membership && ! (bool) $request->user()->is_system, 404);

        if ((bool) config('location-governance.elections_enabled', false)) {
            abort_unless(
                $group->governance_area_id !== null
                    && $group->dimension_key !== null
                    && $group->dimension_value_key !== null
                    && $group->governanceArea()->where('area_kind', 'official')->where('status', 'active')->exists(),
                404,
            );
        }

        $election = Election::query()
            ->where('group_id', $group->id)
            ->where('lifecycle_status', ElectionLifecycleStatus::Open)
            ->orderByDesc('cycle_number')
            ->orderByDesc('id')
            ->first();

        if (! $election) {
            return response()->json([
                'election' => null,
                'membership' => ['role' => (int) $membership->role],
                'permissions' => ['can_vote' => false],
            ]);
        }

        $eligible = ElectionEligibilitySnapshot::query()
            ->where('election_id', $election->id)
            ->where('user_id', $request->user()->id)
            ->where('voter_eligible', true)
            ->exists();

        $votes = Vote::query()
            ->where('election_id', $election->id)
            ->where('voter_id', $request->user()->id)
            ->get();

        return response()->json([
            'election' => [
                'id' => (int) $election->id,
                'group_id' => (int) $election->group_id,
                'cycle_number' => (int) $election->cycle_number,
                'status' => $election->lifecycle_status?->value ?? (string) $election->lifecycle_status,
                'starts_at' => $election->starts_at?->toISOString(),
                'ends_at' => $election->ends_at?->toISOString(),
            ],
            'membership' => ['role' => (int) $membership->role],
            'permissions' => ['can_vote' => $eligible],
            'ballot' => [
                'manager_user_ids' => $votes->where('position', '1')->pluck('candidate_user_id')->map(fn ($id) => (int) $id)->values()->all(),
                'inspector_user_ids' => $votes->where('position', '0')->pluck('candidate_user_id')->map(fn ($id) => (int) $id)->values()->all(),
            ],
        ]);
    }

    public function ballot(Request $request, Election $election, ElectionBallotService $ballots)
    {
        $membership = GroupUser::query()
            ->where('group_id', $election->group_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 1)
            ->where('role', '!=', 4)
            ->first();
        abort_unless($membership && ! (bool) $request->user()->is_system, 404);

        $validated = $request->validate([
            'manager_user_ids' => ['present', 'array'],
            'manager_user_ids.*' => ['integer', 'min:1'],
            'inspector_user_ids' => ['present', 'array'],
            'inspector_user_ids.*' => ['integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
            'comment_visibility' => ['nullable', Rule::enum(ElectionBallotCommentVisibility::class)],
            'comment_anonymous' => ['nullable', 'boolean'],
            'vote_visibility' => ['nullable', 'array'],
        ]);

        $visibility = isset($validated['comment_visibility'])
            ? ElectionBallotCommentVisibility::from($validated['comment_visibility'])
            : null;

        $result = $ballots->submit(
            $election,
            (int) $request->user()->id,
            $validated['manager_user_ids'],
            $validated['inspector_user_ids'],
            (string) $request->header('Idempotency-Key'),
            $validated['comment'] ?? null,
            $visibility,
            $validated['vote_visibility'] ?? [],
            (bool) ($validated['comment_anonymous'] ?? false),
        );

        return response()->json($result);
    }
}
