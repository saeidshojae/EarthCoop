<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionPosition;
use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\Vote;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Privacy-safe aggregate reporting for E0 §7.3. */
class ElectionCandidateReportService
{
    public function report(
        Election $election,
        int $candidateUserId,
        string $position,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): array {
        $to = CarbonImmutable::parse($to ?? now());
        $from = CarbonImmutable::parse($from ?? $to->subDays(28));

        $policy = $election->policyVersion;
        $minDistinct = max(2, (int) ($policy?->report_min_distinct_voters ?? 10));
        $bucketDays = max(1, (int) ($policy?->report_bucket_days ?? 7));
        $meaningfulNet = max(1, (int) ($policy?->meaningful_trend_min_net_change ?? 3));

        $canonicalPosition = $position === ElectionPosition::Inspector->value
            ? ElectionPosition::Inspector
            : ElectionPosition::Manager;
        $positionValues = [$canonicalPosition->value, (string) $canonicalPosition->legacyVotePosition()];

        $currentVoteRows = Vote::query()
            ->where('election_id', $election->id)
            ->where('candidate_user_id', $candidateUserId)
            ->whereIn('position', $positionValues)
            ->get(['voter_id']);
        $currentVotes = $currentVoteRows->count();
        $currentVoterIds = $currentVoteRows->pluck('voter_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $cutoff = $this->selectionCutoff($election, $canonicalPosition);
        $base = [
            'election_id' => (int) $election->id,
            'candidate_user_id' => $candidateUserId,
            'position' => $canonicalPosition->value,
            'current_votes' => $currentVotes,
            'selection_cutoff_votes' => $cutoff,
            'margin_to_selection_cutoff' => $cutoff === null ? null : $currentVotes - $cutoff,
            'privacy' => [
                'min_distinct_voters' => $minDistinct,
                'min_bucket_days' => $bucketDays,
            ],
        ];

        $spanDays = max(0, $from->diffInDays($to));
        if ($spanDays < $bucketDays) {
            return $base + ['details_suppressed' => true, 'suppression_reason' => 'reporting_window_too_small'];
        }

        $events = ElectionBallotEvent::query()
            ->where('election_id', $election->id)
            ->whereBetween('occurred_at', [$from, $to])
            ->where(function ($query) use ($candidateUserId) {
                $query->where('candidate_user_id', $candidateUserId)
                    ->orWhere('previous_candidate_user_id', $candidateUserId);
            })
            ->orderBy('occurred_at')
            ->get([
                'voter_id', 'event_type', 'candidate_user_id', 'previous_candidate_user_id',
                'position', 'previous_position', 'occurred_at',
            ]);

        // A candidate can move between manager/inspector across ballot edits.
        // Reporting thresholds, trends and retention must therefore count only
        // events that actually change the requested candidate+position pair.
        $positionEvents = $events->filter(
            fn (ElectionBallotEvent $event) => $this->deltaForCandidatePosition($event, $candidateUserId, $canonicalPosition) !== 0
        )->values();

        $distinctVoters = $positionEvents->pluck('voter_id')->map(fn ($id) => (int) $id)->unique()->count();
        if ($distinctVoters < $minDistinct) {
            return $base + [
                'details_suppressed' => true,
                'suppression_reason' => 'distinct_voter_threshold_not_met',
                'distinct_voters' => null,
            ];
        }

        $bucketSeconds = $bucketDays * 86400;
        $origin = $from->startOfDay();
        $buckets = [];
        $inflow = 0;
        $outflow = 0;

        foreach ($positionEvents as $event) {
            $delta = $this->deltaForCandidatePosition($event, $candidateUserId, $canonicalPosition);
            $seconds = max(0, CarbonImmutable::parse($event->occurred_at)->getTimestamp() - $origin->getTimestamp());
            $bucketIndex = intdiv($seconds, $bucketSeconds);
            $bucketStart = $origin->addDays($bucketIndex * $bucketDays)->toDateString();
            $buckets[$bucketStart] ??= ['start' => $bucketStart, 'inflow' => 0, 'outflow' => 0, 'net' => 0];

            if ($delta > 0) {
                $buckets[$bucketStart]['inflow']++;
                $inflow++;
            } else {
                $buckets[$bucketStart]['outflow']++;
                $outflow++;
            }
            $buckets[$bucketStart]['net'] += $delta;
        }

        $net = $inflow - $outflow;
        $retention = $this->retentionRate(
            $positionEvents,
            $candidateUserId,
            $canonicalPosition,
            $currentVoterIds,
            $minDistinct,
        );

        return $base + [
            'details_suppressed' => false,
            'suppression_reason' => null,
            'distinct_voters' => $distinctVoters,
            'window' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'bucket_days' => $bucketDays,
            ],
            'inflow' => $inflow,
            'outflow' => $outflow,
            'net_change' => $net,
            'trend_buckets' => array_values($buckets),
            'retention_rate' => $retention['rate'],
            'retention_suppressed' => $retention['suppressed'],
            'meaningful_trend' => abs($net) >= $meaningfulNet,
            'meaningful_trend_threshold' => $meaningfulNet,
        ];
    }

    private function retentionRate(
        $events,
        int $candidateUserId,
        ElectionPosition $position,
        array $currentVoterIds,
        int $minDistinct,
    ): array {
        $atStart = array_fill_keys($currentVoterIds, true);

        foreach ($events->sortByDesc('occurred_at') as $event) {
            $voterId = (int) $event->voter_id;
            $delta = $this->deltaForCandidatePosition($event, $candidateUserId, $position);
            if ($delta > 0) {
                // Reverse a gain: before this event the voter was not supporting
                // this candidate in this position.
                unset($atStart[$voterId]);
            } elseif ($delta < 0) {
                // Reverse a loss: before this event the voter did support it.
                $atStart[$voterId] = true;
            }
        }

        $startIds = array_map('intval', array_keys($atStart));
        if (count($startIds) < $minDistinct) {
            return ['rate' => null, 'suppressed' => true];
        }

        $currentLookup = array_fill_keys($currentVoterIds, true);
        $retained = 0;
        foreach ($startIds as $voterId) {
            if (isset($currentLookup[$voterId])) $retained++;
        }

        return ['rate' => round($retained / count($startIds), 4), 'suppressed' => false];
    }

    private function selectionCutoff(Election $election, ElectionPosition $position): ?int
    {
        $seatCount = $position === ElectionPosition::Inspector
            ? (int) ($election->policyVersion?->inspector_count ?? 0)
            : (int) ($election->policyVersion?->manager_count ?? 0);
        if ($seatCount <= 0) return null;

        $counts = Vote::query()
            ->where('election_id', $election->id)
            ->whereIn('position', [$position->value, (string) $position->legacyVotePosition()])
            ->whereNotNull('candidate_user_id')
            ->selectRaw('candidate_user_id, COUNT(*) as vote_count')
            ->groupBy('candidate_user_id')
            ->orderByDesc('vote_count')
            ->pluck('vote_count')->map(fn ($count) => (int) $count)->values();

        if ($counts->isEmpty()) return 0;
        return (int) ($counts->get(min($seatCount, $counts->count()) - 1) ?? 0);
    }

    private function deltaForCandidatePosition(
        ElectionBallotEvent $event,
        int $candidateUserId,
        ElectionPosition $position,
    ): int {
        $target = $position->value;

        if ($event->event_type === 'vote_cast'
            && (int) $event->candidate_user_id === $candidateUserId
            && (string) $event->position === $target) {
            return 1;
        }

        if ($event->event_type === 'vote_withdrawn'
            && (int) $event->previous_candidate_user_id === $candidateUserId
            && (string) $event->previous_position === $target) {
            return -1;
        }

        if ($event->event_type === 'vote_changed'
            && (int) $event->candidate_user_id === $candidateUserId
            && (int) $event->previous_candidate_user_id === $candidateUserId) {
            $before = (string) $event->previous_position === $target;
            $after = (string) $event->position === $target;
            if (! $before && $after) return 1;
            if ($before && ! $after) return -1;
        }

        return 0;
    }
}
