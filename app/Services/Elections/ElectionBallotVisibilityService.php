<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionBallotCommentVisibility;
use App\Enums\Elections\ElectionVoteVisibility;
use App\Models\ElectionAppointment;
use App\Models\ElectionBallotEvent;
use App\Models\GroupUser;
use App\Models\User;

/**
 * Canonical ordinary-read authorization for E0 §7 ballot privacy.
 *
 * Protected audit access is intentionally NOT represented here. Any future
 * audit/review endpoint must use a separate, explicitly privileged service so
 * ordinary UI/report code cannot accidentally inherit audit-level identity.
 */
class ElectionBallotVisibilityService
{
    public function canViewVoteIdentity(ElectionBallotEvent $event, User $viewer): bool
    {
        if ((int) $event->voter_id === (int) $viewer->id) {
            return true;
        }

        return match ($this->voteVisibility($event)) {
            ElectionVoteVisibility::Confidential => false,
            ElectionVoteVisibility::AllMembers => $this->isActiveGroupMember($event, $viewer),
            ElectionVoteVisibility::ElectedOfficials => $this->isActiveElectedOfficial($event, $viewer),
        };
    }

    public function canViewComment(ElectionBallotEvent $event, User $viewer): bool
    {
        if ($event->comment === null || trim((string) $event->comment) === '') {
            return false;
        }

        if ((int) $event->voter_id === (int) $viewer->id) {
            return true;
        }

        $visibility = $event->comment_visibility;
        if (is_string($visibility)) {
            $visibility = ElectionBallotCommentVisibility::from($visibility);
        }
        if (! $visibility instanceof ElectionBallotCommentVisibility) {
            return false;
        }

        return match ($visibility) {
            ElectionBallotCommentVisibility::AllMembers => $this->isActiveGroupMember($event, $viewer),
            ElectionBallotCommentVisibility::ElectedOfficials => $this->isActiveElectedOfficial($event, $viewer),
            ElectionBallotCommentVisibility::SubjectOnly => $this->isCommentSubject($event, $viewer),
        };
    }

    public function canViewCommentAuthor(ElectionBallotEvent $event, User $viewer): bool
    {
        if (! $this->canViewComment($event, $viewer)) {
            return false;
        }

        if ((int) $event->voter_id === (int) $viewer->id) {
            return true;
        }

        return ! (bool) $event->comment_anonymous;
    }

    /**
     * Safe ordinary-read identity projection. Null means the identity MUST NOT
     * be exposed by UI/API/report code.
     */
    public function visibleVoterId(ElectionBallotEvent $event, User $viewer): ?int
    {
        return $this->canViewVoteIdentity($event, $viewer) ? (int) $event->voter_id : null;
    }

    /**
     * Safe ordinary-read comment-author projection. This is intentionally
     * independent of vote identity visibility as required by E0 §7.2.
     */
    public function visibleCommentAuthorId(ElectionBallotEvent $event, User $viewer): ?int
    {
        return $this->canViewCommentAuthor($event, $viewer) ? (int) $event->voter_id : null;
    }

    private function voteVisibility(ElectionBallotEvent $event): ElectionVoteVisibility
    {
        $visibility = $event->vote_visibility;
        if (is_string($visibility)) {
            return ElectionVoteVisibility::from($visibility);
        }

        return $visibility instanceof ElectionVoteVisibility
            ? $visibility
            : ElectionVoteVisibility::Confidential;
    }

    private function isActiveGroupMember(ElectionBallotEvent $event, User $viewer): bool
    {
        return GroupUser::query()
            ->where('group_id', $event->election->group_id)
            ->where('user_id', $viewer->id)
            ->where('status', 1)
            ->exists();
    }

    private function isActiveElectedOfficial(ElectionBallotEvent $event, User $viewer): bool
    {
        return ElectionAppointment::query()
            ->where('group_id', $event->election->group_id)
            ->where('user_id', $viewer->id)
            ->where('status', 'active')
            ->whereIn('position', ['manager', 'inspector'])
            ->whereNull('ended_at')
            ->exists();
    }

    private function isCommentSubject(ElectionBallotEvent $event, User $viewer): bool
    {
        $subjectId = $event->candidate_user_id ?? $event->previous_candidate_user_id;
        return $subjectId !== null && (int) $subjectId === (int) $viewer->id;
    }
}
