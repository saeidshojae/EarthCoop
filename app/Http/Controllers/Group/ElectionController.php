<?php

namespace App\Http\Controllers\Group;

use App\Enums\Elections\ElectionBallotCommentVisibility;
use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionVoteVisibility;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Group;
use App\Services\Elections\ElectionBallotService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ElectionController extends Controller
{
    public function __construct(
        private readonly ElectionBallotService $ballots,
    ) {
    }

    public function submitVote(Request $request, Group $group)
    {
        $inputs = $request->validate([
            'inspector' => 'nullable|array',
            'manager' => 'nullable|array',
            'vote_visibility' => 'nullable|array',
            'vote_visibility.*' => [
                Rule::in(array_map(
                    fn (ElectionVoteVisibility $visibility) => $visibility->value,
                    ElectionVoteVisibility::cases(),
                )),
            ],
            'comment' => 'nullable|string|max:4000',
            'comment_visibility' => [
                'nullable',
                Rule::in(array_map(
                    fn (ElectionBallotCommentVisibility $visibility) => $visibility->value,
                    ElectionBallotCommentVisibility::cases(),
                )),
            ],
            'comment_anonymous' => 'nullable|boolean',
        ]);

        $election = Election::query()
            ->where('group_id', $group->id)
            ->where('lifecycle_status', ElectionLifecycleStatus::Open->value)
            ->orderByDesc('id')
            ->first();

        if ($election === null) {
            throw ValidationException::withMessages([
                'election' => 'در حال حاضر انتخابات بازی برای این گروه وجود ندارد.',
            ]);
        }

        $commentVisibility = isset($inputs['comment_visibility'])
            ? ElectionBallotCommentVisibility::from($inputs['comment_visibility'])
            : null;

        $result = $this->ballots->submit(
            $election,
            (int) auth()->id(),
            $inputs['manager'] ?? [],
            $inputs['inspector'] ?? [],
            $request->header('Idempotency-Key') ?: null,
            $inputs['comment'] ?? null,
            $commentVisibility,
            $inputs['vote_visibility'] ?? [],
            (bool) ($inputs['comment_anonymous'] ?? false),
        );

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'رأی شما با موفقیت ثبت شد.',
                'ballot' => $result,
            ]);
        }

        return redirect()->back()->with('success', 'رأی شما با موفقیت ثبت شد.');
    }

    /**
     * Retired compatibility endpoint.
     *
     * Election closing, snapshotting, tallying and offer creation are owned
     * exclusively by the systemic scheduler/domain lifecycle. Keeping this
     * adapter non-mutating prevents old clients/bookmarks from bypassing the
     * canonical state machine while the legacy route declaration remains in
     * the compatibility route file.
     */
    public function finishElection(Election $election)
    {
        $this->authorize('manageSession', $election->group);

        return response()->json([
            'status' => 'retired',
            'message' => 'پایان دستی انتخابات بازنشسته شده است؛ چرخه فقط توسط سامانه انتخابات متوقف و شمارش می‌شود.',
        ], 410);
    }
}
