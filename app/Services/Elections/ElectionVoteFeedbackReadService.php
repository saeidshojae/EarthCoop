<?php

namespace App\Services\Elections;

use App\Models\ElectionAppointment;
use App\Models\ElectionVoteFeedback;
use App\Models\GroupUser;
use App\Models\User;

/**
 * Ordinary UI/API projection for E0 §7.2 feedback.
 *
 * It never exposes ballot_event_id, audit timestamps, or the author identity
 * of anonymous feedback. Non-authors only receive approved feedback, and only
 * within the visibility scope chosen by the voter.
 */
class ElectionVoteFeedbackReadService
{
    public function present(ElectionVoteFeedback $feedback, User $viewer): ?array
    {
        $isAuthor = (int) $feedback->author_user_id === (int) $viewer->id;

        if (! $isAuthor && $feedback->moderation_status !== 'approved') {
            return null;
        }

        if (! $isAuthor && ! $this->withinVisibilityScope($feedback, $viewer)) {
            return null;
        }

        return [
            'id' => (int) $feedback->id,
            'election_id' => (int) $feedback->election_id,
            'subject_user_id' => $feedback->subject_user_id !== null ? (int) $feedback->subject_user_id : null,
            'event_type' => $feedback->event_type,
            'visibility' => $feedback->visibility,
            'anonymous' => (bool) $feedback->anonymous,
            'body' => $feedback->body,
            'author_user_id' => ($isAuthor || ! $feedback->anonymous) ? (int) $feedback->author_user_id : null,
            'moderation_status' => $isAuthor ? $feedback->moderation_status : 'approved',
            // Calendar bucket only; raw ballot-event time is intentionally absent.
            'public_bucket_start' => $feedback->public_bucket_start?->toDateString(),
        ];
    }

    private function withinVisibilityScope(ElectionVoteFeedback $feedback, User $viewer): bool
    {
        $groupId = (int) $feedback->election->group_id;

        return match ($feedback->visibility) {
            'all_members' => GroupUser::query()
                ->where('group_id', $groupId)
                ->where('user_id', $viewer->id)
                ->where('status', 1)
                ->exists(),
            'elected_officials' => ElectionAppointment::query()
                ->where('group_id', $groupId)
                ->where('user_id', $viewer->id)
                ->where('status', 'active')
                ->whereIn('position', ['manager', 'inspector'])
                ->whereNull('ended_at')
                ->exists(),
            'subject_only' => $feedback->subject_user_id !== null
                && (int) $feedback->subject_user_id === (int) $viewer->id,
            default => false,
        };
    }
}
