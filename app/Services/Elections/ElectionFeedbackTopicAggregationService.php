<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionBallotCommentVisibility;
use App\Models\Election;
use App\Models\ElectionVoteFeedback;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Privacy-safe coarse topic aggregation for E0 §7.3.
 *
 * Normal aggregate() respects the viewer's §7.2 visibility. publicAggregate()
 * is stricter: it uses only feedback explicitly shared with all group members,
 * so a candidate's public topic response cannot disclose a private topic.
 */
class ElectionFeedbackTopicAggregationService
{
    public function __construct(private readonly ElectionVoteFeedbackReadService $readService) {}

    public function aggregate(
        Election $election,
        int $subjectUserId,
        User $viewer,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): array {
        return $this->aggregateInternal($election, $subjectUserId, $viewer, $from, $to, false);
    }

    public function publicAggregate(
        Election $election,
        int $subjectUserId,
        User $viewer,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): array {
        return $this->aggregateInternal($election, $subjectUserId, $viewer, $from, $to, true);
    }

    private function aggregateInternal(
        Election $election,
        int $subjectUserId,
        User $viewer,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        bool $publicOnly,
    ): array {
        $to = CarbonImmutable::parse($to ?? now());
        $from = CarbonImmutable::parse($from ?? $to->subDays(28));
        $policy = $election->policyVersion;
        $minDistinct = max(2, (int) ($policy?->report_min_distinct_voters ?? 10));
        $bucketDays = max(1, (int) ($policy?->report_bucket_days ?? 7));

        $base = [
            'election_id' => (int) $election->id,
            'subject_user_id' => $subjectUserId,
            'min_distinct_authors' => $minDistinct,
            'min_bucket_days' => $bucketDays,
            'aggregation_window_start' => $from->toDateString(),
            'aggregation_window_end' => $to->toDateString(),
            'public_only' => $publicOnly,
        ];

        if ($from->diffInDays($to) < $bucketDays) {
            return $base + [
                'topics_suppressed' => true,
                'suppression_reason' => 'reporting_window_too_small',
                'topics' => [],
            ];
        }

        $query = ElectionVoteFeedback::query()
            ->where('election_id', $election->id)
            ->where('subject_user_id', $subjectUserId)
            ->where('moderation_status', 'approved')
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$from, $to]);

        if ($publicOnly) {
            $query->where('visibility', ElectionBallotCommentVisibility::AllMembers->value);
        }

        $feedback = $query->get();
        $visible = $feedback->filter(fn (ElectionVoteFeedback $item) => $this->readService->present($item, $viewer) !== null);
        $distinctAuthors = $visible->pluck('author_user_id')->map(fn ($id) => (int) $id)->unique()->count();

        if ($distinctAuthors < $minDistinct) {
            return $base + [
                'topics_suppressed' => true,
                'suppression_reason' => 'distinct_author_threshold_not_met',
                'topics' => [],
            ];
        }

        $counts = [];
        foreach ($visible as $item) {
            foreach ($this->classify((string) $item->body) as $topic) {
                $counts[$topic] = ($counts[$topic] ?? 0) + 1;
            }
        }
        arsort($counts);

        return $base + [
            'topics_suppressed' => false,
            'suppression_reason' => null,
            'topics' => collect($counts)
                ->map(fn (int $count, string $topic) => ['topic' => $topic, 'count' => $count])
                ->values()
                ->all(),
        ];
    }

    private function classify(string $body): array
    {
        $text = mb_strtolower($body);
        $taxonomy = [
            'responsiveness' => ['پاسخگو', 'پاسخ گ', 'پاسخ‌گ', 'پیگیری', 'رسیدگی', 'جواب'],
            'transparency' => ['شفاف', 'گزارش', 'اطلاع‌رسان', 'اطلاع رسان', 'پنهان', 'ابهام'],
            'performance' => ['عملکرد', 'کارآمد', 'نتیجه', 'اجرا', 'اقدام', 'ضعیف', 'موفق'],
            'fairness' => ['عدالت', 'منصف', 'تبعیض', 'برابر', 'حق'],
            'collaboration' => ['همکاری', 'مشارکت', 'همفکری', 'گفتگو', 'گفت‌وگو', 'تعامل'],
        ];

        $topics = [];
        foreach ($taxonomy as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $topics[] = $topic;
                    break;
                }
            }
        }

        return $topics === [] ? ['other'] : $topics;
    }
}
