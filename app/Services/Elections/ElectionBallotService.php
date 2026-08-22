<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionBallotCommentVisibility;
use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Enums\Elections\ElectionVoteVisibility;
use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ElectionBallotService
{
    private const REQUEST_UUID_MAX_LENGTH = 96;
    private const COMMENT_MAX_LENGTH = 4000;

    public function __construct(
        private readonly ElectionPolicyResolver $policyResolver,
        private readonly ElectionEligibilitySnapshotService $eligibilitySnapshots,
    ) {}

    public function submit(
        Election $election,
        int $voterId,
        array $managerUserIds,
        array $inspectorUserIds,
        ?string $requestUuid = null,
        ?string $comment = null,
        ?ElectionBallotCommentVisibility $commentVisibility = null,
        array $voteVisibilityByCandidate = [],
        bool $commentAnonymous = false,
    ): array {
        return DB::transaction(function () use (
            $election, $voterId, $managerUserIds, $inspectorUserIds, $requestUuid,
            $comment, $commentVisibility, $voteVisibilityByCandidate, $commentAnonymous,
        ): array {
            $lockedElection = Election::query()->lockForUpdate()->findOrFail($election->id);
            if ((int) $lockedElection->group_id !== (int) $election->group_id) {
                throw ValidationException::withMessages(['election' => 'انتخابات با گروه موردنظر همخوانی ندارد.']);
            }
            if ($lockedElection->lifecycle_status !== ElectionLifecycleStatus::Open) {
                throw ValidationException::withMessages(['election' => 'این انتخابات در وضعیت دریافت رأی نیست.']);
            }

            $managerIds = $this->normaliseIds($managerUserIds, 'manager');
            $inspectorIds = $this->normaliseIds($inspectorUserIds, 'inspector');
            [$normalisedComment, $normalisedCommentVisibility] = $this->normaliseComment($comment, $commentVisibility);

            $duplicatesAcrossPositions = array_values(array_intersect($managerIds, $inspectorIds));
            if ($duplicatesAcrossPositions !== []) {
                throw ValidationException::withMessages(['ballot' => 'یک عضو نمی‌تواند همزمان برای نقش مدیر و بازرس انتخاب شود.']);
            }

            try {
                $policy = $this->policyResolver->resolveForElection($lockedElection);
            } catch (RuntimeException) {
                // Compatibility only for a legacy pre-versioned open election.
                $policy = $this->policyResolver->resolveForGroup($lockedElection->group);
            }
            if (count($managerIds) > $this->policyResolver->managerSeatCount($policy)) {
                throw ValidationException::withMessages(['manager' => 'تعداد انتخاب‌های مدیر بیشتر از ظرفیت مجاز این انتخابات است.']);
            }
            if (count($inspectorIds) > $this->policyResolver->inspectorSeatCount($policy)) {
                throw ValidationException::withMessages(['inspector' => 'تعداد انتخاب‌های بازرس بیشتر از ظرفیت مجاز این انتخابات است.']);
            }

            $selectedIds = array_values(array_unique(array_merge($managerIds, $inspectorIds)));

            // E0 continuous elections admit members who join while the current
            // collection window is already open. Existing snapshot rows are
            // never rewritten; missing current members are appended before the
            // ballot is validated, and enrollment becomes impossible at stop.
            $this->eligibilitySnapshots->enrollOpenElectionMembers(
                $lockedElection,
                array_values(array_unique(array_merge([$voterId], $selectedIds))),
            );

            $voterSnapshot = ElectionEligibilitySnapshot::query()
                ->where('election_id', $lockedElection->id)->where('user_id', $voterId)->first();
            if ($voterSnapshot === null || ! $voterSnapshot->voter_eligible) {
                throw ValidationException::withMessages(['voter' => 'شما در این بازه انتخابات پیوسته واجد شرایط رأی دادن نیستید.']);
            }

            if ($selectedIds !== []) {
                $selectableIds = ElectionEligibilitySnapshot::query()
                    ->where('election_id', $lockedElection->id)
                    ->whereIn('user_id', $selectedIds)
                    ->where('selectable_eligible', true)
                    ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
                $invalidIds = array_values(array_diff($selectedIds, $selectableIds));
                if ($invalidIds !== []) {
                    throw ValidationException::withMessages(['ballot' => 'یک یا چند عضو انتخاب‌شده در این بازه انتخابات پیوسته واجد شرایط انتخاب شدن نیستند.']);
                }
            }

            $desiredVisibility = $this->normaliseVoteVisibility($selectedIds, $voteVisibilityByCandidate);

            $currentVotes = Vote::query()
                ->where('election_id', $lockedElection->id)->where('voter_id', $voterId)
                ->lockForUpdate()->get();
            if ($currentVotes->contains(fn (Vote $vote) => $vote->candidate_user_id === null)) {
                throw ValidationException::withMessages(['ballot' => 'رأی تاریخی شما شامل شناسه حل‌نشده است و تا تطبیق داده‌ها قابل تغییر نیست.']);
            }

            $current = [];
            foreach ($currentVotes as $vote) {
                try {
                    $position = ElectionPosition::fromLegacyVotePosition($vote->position ?? '');
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages(['ballot' => 'رأی تاریخی شما شامل نقش انتخاباتی نامعتبر است و تا تطبیق داده‌ها قابل تغییر نیست.']);
                }
                $candidateUserId = (int) $vote->candidate_user_id;
                if (isset($current[$candidateUserId])) {
                    throw ValidationException::withMessages(['ballot' => 'رأی تاریخی شما شامل انتخاب تکراری است و تا تطبیق داده‌ها قابل تغییر نیست.']);
                }
                $current[$candidateUserId] = [
                    'position' => $position,
                    'visibility' => $vote->vote_visibility instanceof ElectionVoteVisibility
                        ? $vote->vote_visibility
                        : ElectionVoteVisibility::Confidential,
                ];
            }

            $desired = [];
            foreach ($managerIds as $candidateUserId) {
                $desired[$candidateUserId] = ['position' => ElectionPosition::Manager, 'visibility' => $desiredVisibility[$candidateUserId]];
            }
            foreach ($inspectorIds as $candidateUserId) {
                $desired[$candidateUserId] = ['position' => ElectionPosition::Inspector, 'visibility' => $desiredVisibility[$candidateUserId]];
            }

            $uuid = $this->normaliseRequestUuid($requestUuid);
            $occurredAt = now();

            foreach ($current as $candidateUserId => $old) {
                if (! isset($desired[$candidateUserId])) {
                    $this->appendEvent($lockedElection->id, $voterId, 'vote_withdrawn', null, $candidateUserId, null,
                        $old['position'], $old['visibility'], $uuid, $occurredAt, $normalisedComment,
                        $normalisedCommentVisibility, $commentAnonymous);
                    continue;
                }

                $new = $desired[$candidateUserId];
                if ($new['position'] !== $old['position'] || $new['visibility'] !== $old['visibility']) {
                    $this->appendEvent($lockedElection->id, $voterId, 'vote_changed', $candidateUserId, $candidateUserId,
                        $new['position'], $old['position'], $new['visibility'], $uuid, $occurredAt, $normalisedComment,
                        $normalisedCommentVisibility, $commentAnonymous);
                }
            }

            foreach ($desired as $candidateUserId => $new) {
                if (! isset($current[$candidateUserId])) {
                    $this->appendEvent($lockedElection->id, $voterId, 'vote_cast', $candidateUserId, null,
                        $new['position'], null, $new['visibility'], $uuid, $occurredAt, $normalisedComment,
                        $normalisedCommentVisibility, $commentAnonymous);
                }
            }

            Vote::query()->where('election_id', $lockedElection->id)->where('voter_id', $voterId)->delete();
            foreach ($desired as $candidateUserId => $new) {
                Vote::create([
                    'election_id' => $lockedElection->id,
                    'voter_id' => $voterId,
                    'candidate_id' => $candidateUserId,
                    'candidate_user_id' => $candidateUserId,
                    'position' => $new['position']->legacyVotePosition(),
                    'vote_visibility' => $new['visibility']->value,
                ]);
            }

            return [
                'election_id' => (int) $lockedElection->id,
                'voter_id' => $voterId,
                'manager_user_ids' => $managerIds,
                'inspector_user_ids' => $inspectorIds,
                'vote_visibility' => array_map(fn (ElectionVoteVisibility $v) => $v->value, $desiredVisibility),
                'request_uuid' => $uuid,
            ];
        });
    }

    private function normaliseIds(array $ids, string $field): array
    {
        $normalised = [];
        foreach ($ids as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id <= 0) {
                throw ValidationException::withMessages([$field => 'شناسه عضو انتخاب‌شده نامعتبر است.']);
            }
            $normalised[] = (int) $id;
        }
        if (count($normalised) !== count(array_unique($normalised))) {
            throw ValidationException::withMessages([$field => 'یک عضو در یک نقش نمی‌تواند بیش از یک بار انتخاب شود.']);
        }
        return array_values($normalised);
    }

    private function normaliseVoteVisibility(array $selectedIds, array $raw): array
    {
        $selectedLookup = array_fill_keys($selectedIds, true);
        $result = [];
        foreach ($raw as $candidateId => $visibility) {
            if (filter_var($candidateId, FILTER_VALIDATE_INT) === false || ! isset($selectedLookup[(int) $candidateId])) {
                throw ValidationException::withMessages(['vote_visibility' => 'سطح افشای رأی برای عضو خارج از برگه رأی ارسال شده است.']);
            }
            try {
                $result[(int) $candidateId] = $visibility instanceof ElectionVoteVisibility
                    ? $visibility
                    : ElectionVoteVisibility::from((string) $visibility);
            } catch (\ValueError) {
                throw ValidationException::withMessages(['vote_visibility' => 'سطح افشای رأی نامعتبر است.']);
            }
        }
        foreach ($selectedIds as $candidateId) {
            $result[$candidateId] ??= ElectionVoteVisibility::Confidential;
        }
        return $result;
    }

    private function normaliseRequestUuid(?string $requestUuid): string
    {
        $uuid = trim((string) ($requestUuid ?: Str::uuid()));
        if ($uuid === '') {
            $uuid = (string) Str::uuid();
        }
        if (mb_strlen($uuid) > self::REQUEST_UUID_MAX_LENGTH) {
            throw ValidationException::withMessages(['idempotency_key' => 'شناسه یکتای درخواست بیش از حد مجاز طول دارد.']);
        }
        return $uuid;
    }

    private function normaliseComment(?string $comment, ?ElectionBallotCommentVisibility $visibility): array
    {
        $normalised = $comment === null ? null : trim($comment);
        if ($normalised === '') {
            $normalised = null;
        }
        if ($normalised === null) {
            return [null, null];
        }
        if (mb_strlen($normalised) > self::COMMENT_MAX_LENGTH) {
            throw ValidationException::withMessages(['comment' => 'متن توضیح رأی بیش از حد مجاز طول دارد.']);
        }
        if ($visibility === null) {
            throw ValidationException::withMessages(['comment_visibility' => 'برای توضیح رأی باید سطح نمایش مشخص شود.']);
        }
        return [$normalised, $visibility];
    }

    private function appendEvent(
        int $electionId,
        int $voterId,
        string $eventType,
        ?int $candidateUserId,
        ?int $previousCandidateUserId,
        ?ElectionPosition $position,
        ?ElectionPosition $previousPosition,
        ElectionVoteVisibility $voteVisibility,
        string $requestUuid,
        $occurredAt,
        ?string $comment,
        ?ElectionBallotCommentVisibility $commentVisibility,
        bool $commentAnonymous,
    ): void {
        ElectionBallotEvent::create([
            'election_id' => $electionId,
            'voter_id' => $voterId,
            'event_type' => $eventType,
            'candidate_user_id' => $candidateUserId,
            'previous_candidate_user_id' => $previousCandidateUserId,
            'position' => $position?->value,
            'previous_position' => $previousPosition?->value,
            'vote_visibility' => $voteVisibility->value,
            'comment' => $comment,
            'comment_visibility' => $commentVisibility?->value,
            'comment_anonymous' => $comment !== null && $commentAnonymous,
            'request_uuid' => $requestUuid,
            'metadata' => ['source' => 'ballot_v2'],
            'occurred_at' => $occurredAt,
        ]);
    }
}
