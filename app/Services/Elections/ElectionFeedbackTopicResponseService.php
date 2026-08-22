<?php

namespace App\Services\Elections;

use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionFeedbackTopicResponse;
use App\Models\ElectionTallyResult;
use App\Models\GroupUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

class ElectionFeedbackTopicResponseService
{
    public function __construct(private readonly ElectionFeedbackTopicAggregationService $topics) {}

    public function publish(Election $election, User $subject, string $topicKey, string $body): ElectionFeedbackTopicResponse
    {
        $topicKey = trim($topicKey);
        $body = trim($body);
        if ($topicKey === '' || $body === '') {
            throw new InvalidArgumentException('Topic and response body are required.');
        }
        $this->assertSubjectMayRespond($election, $subject);

        $to = CarbonImmutable::now();
        $from = $to->subDays(28);
        $aggregate = $this->topics->publicAggregate($election, $subject->id, $subject, $from, $to);
        if ($aggregate['topics_suppressed']) {
            throw new RuntimeException('Public topic response is unavailable while the privacy threshold suppresses aggregation.');
        }
        $topic = collect($aggregate['topics'])->firstWhere('topic', $topicKey);
        if ($topic === null) {
            throw new RuntimeException('Candidate may respond only to a currently privacy-safe public aggregate topic.');
        }

        return ElectionFeedbackTopicResponse::create([
            'election_id' => $election->id,
            'subject_user_id' => $subject->id,
            'topic_key' => $topicKey,
            'aggregation_window_start' => $aggregate['aggregation_window_start'],
            'aggregation_window_end' => $aggregate['aggregation_window_end'],
            'aggregate_count' => (int) $topic['count'],
            'min_distinct_authors' => (int) $aggregate['min_distinct_authors'],
            'min_bucket_days' => (int) $aggregate['min_bucket_days'],
            'body' => $body,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function publicForMember(Election $election, User $viewer, ?int $subjectUserId = null): Collection
    {
        $this->assertActiveMember($election, $viewer);
        return ElectionFeedbackTopicResponse::query()
            ->where('election_id', $election->id)
            ->where('status', 'published')
            ->when($subjectUserId !== null, fn ($query) => $query->where('subject_user_id', $subjectUserId))
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (ElectionFeedbackTopicResponse $response) => [
                'id' => (int) $response->id,
                'subject_user_id' => (int) $response->subject_user_id,
                'topic' => $response->topic_key,
                'aggregate_count' => (int) $response->aggregate_count,
                'body' => $response->body,
                'published_at' => $response->published_at->toISOString(),
            ]);
    }

    private function assertSubjectMayRespond(Election $election, User $subject): void
    {
        $this->assertActiveMember($election, $subject);
        $candidate = ElectionTallyResult::query()
            ->where('election_id', $election->id)
            ->where('candidate_user_id', $subject->id)
            ->exists();
        $official = ElectionAppointment::query()
            ->where('election_id', $election->id)
            ->where('user_id', $subject->id)
            ->where('status', 'active')
            ->exists();
        if (! $candidate && ! $official) {
            throw new RuntimeException('Only a candidate or active elected official may publish a topic response.');
        }
    }

    private function assertActiveMember(Election $election, User $user): void
    {
        $member = GroupUser::query()
            ->where('group_id', $election->group_id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->where('role', '!=', 4)
            ->exists();
        if (! $member || (bool) $user->is_system) {
            throw new RuntimeException('Active group membership is required.');
        }
    }
}
