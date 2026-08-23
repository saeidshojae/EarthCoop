<?php

namespace App\Services\Elections;

use App\Models\ElectionBallotEvent;
use App\Models\ElectionVoteFeedback;
use Carbon\CarbonImmutable;

class ElectionVoteFeedbackService
{
    public function __construct(private readonly ElectionFeedbackModerationService $moderation) {}

    public function capture(ElectionBallotEvent $event): ?ElectionVoteFeedback
    {
        $body = trim((string) $event->comment);
        if ($body === '') {
            return null;
        }

        $existing = ElectionVoteFeedback::query()->where('ballot_event_id', $event->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $screen = $this->moderation->screen($body);
        $approved = $screen['status'] === 'approved';
        $publishedAt = $approved ? now() : null;

        return ElectionVoteFeedback::create([
            'election_id' => $event->election_id,
            'ballot_event_id' => $event->id,
            'author_user_id' => $event->voter_id,
            'subject_user_id' => $event->candidate_user_id ?: $event->previous_candidate_user_id,
            'event_type' => $event->event_type,
            'visibility' => $event->comment_visibility?->value ?? (string) $event->comment_visibility ?: 'subject_only',
            'anonymous' => (bool) $event->comment_anonymous,
            'body' => $body,
            'moderation_status' => $screen['status'],
            'moderation_reasons' => $screen['reasons'],
            'moderation_source' => $screen['source'],
            'moderated_at' => now(),
            'published_at' => $publishedAt,
            'public_bucket_start' => $publishedAt
                ? CarbonImmutable::parse($publishedAt)->startOfWeek()->toDateString()
                : null,
        ]);
    }

    public function approveAfterHumanReview(ElectionVoteFeedback $feedback, int $reviewerUserId): ElectionVoteFeedback
    {
        $publishedAt = now();
        $feedback->forceFill([
            'moderation_status' => 'approved',
            'reviewed_by' => $reviewerUserId,
            'reviewed_at' => $publishedAt,
            'published_at' => $publishedAt,
            'public_bucket_start' => CarbonImmutable::parse($publishedAt)->startOfWeek()->toDateString(),
        ])->save();

        return $feedback->refresh();
    }

    public function rejectAfterHumanReview(ElectionVoteFeedback $feedback, int $reviewerUserId): ElectionVoteFeedback
    {
        $feedback->forceFill([
            'moderation_status' => 'rejected',
            'reviewed_by' => $reviewerUserId,
            'reviewed_at' => now(),
            'published_at' => null,
            'public_bucket_start' => null,
        ])->save();

        return $feedback->refresh();
    }
}
