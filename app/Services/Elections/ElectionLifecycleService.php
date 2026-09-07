<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionLifecycleTransition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ElectionLifecycleService
{
    private const TRANSITIONS = [
        'scheduled' => ['open', 'cancelled'],
        'open' => ['closed', 'cancelled'],
        'closed' => ['tallying', 'cancelled'],
        'tallying' => ['awaiting_acceptance', 'exhausted', 'cancelled'],
        'awaiting_acceptance' => ['appointing', 'exhausted', 'cancelled'],
        'appointing' => ['filled', 'exhausted', 'cancelled'],
        'filled' => [],
        'exhausted' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly ElectionEligibilitySnapshotService $eligibilitySnapshots,
        private readonly ElectionVoteSnapshotService $voteSnapshots,
    ) {
    }

    public function canTransition(ElectionLifecycleStatus $from, ElectionLifecycleStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(
        Election $election,
        ElectionLifecycleStatus $to,
        string $reason,
        string $source = 'system',
        ?int $actorUserId = null,
        ?string $reference = null,
        array $metadata = [],
    ): Election {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Election lifecycle transition reason is required.');
        }
        if (trim($source) === '') {
            throw new InvalidArgumentException('Election lifecycle transition source is required.');
        }

        return DB::transaction(function () use ($election, $to, $reason, $source, $actorUserId, $reference, $metadata): Election {
            /** @var Election $locked */
            $locked = Election::query()->lockForUpdate()->findOrFail($election->getKey());
            $from = $this->currentStatus($locked);

            if ($from === $to) {
                return $locked;
            }
            if (! $this->canTransition($from, $to)) {
                throw new InvalidArgumentException("Invalid election lifecycle transition [{$from->value} -> {$to->value}].");
            }

            if ($to === ElectionLifecycleStatus::Open) {
                $this->eligibilitySnapshots->capture($locked);
            }

            // E0 invariant: the valid ballot set must be frozen at the exact
            // system stop, before tally/application can mutate or outlive it.
            $transitionedAt = now();
            if ($from === ElectionLifecycleStatus::Open && $to === ElectionLifecycleStatus::Closed) {
                $this->voteSnapshots->capture($locked, $transitionedAt);
            }

            $locked->lifecycle_status = $to;
            if (! in_array($to, [ElectionLifecycleStatus::Scheduled, ElectionLifecycleStatus::Open], true)) {
                $locked->is_closed = true;
            } elseif ($to === ElectionLifecycleStatus::Open) {
                $locked->is_closed = false;
            }
            $locked->save();

            ElectionLifecycleTransition::create([
                'election_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => trim($reason),
                'source' => trim($source),
                'actor_user_id' => $actorUserId,
                'reference' => $reference,
                'metadata' => $metadata ?: null,
                'transitioned_at' => $transitionedAt,
            ]);

            return $locked->refresh();
        }, 3);
    }

    public function currentStatus(Election $election): ElectionLifecycleStatus
    {
        $raw = $election->getRawOriginal('lifecycle_status')
            ?? $election->getAttributes()['lifecycle_status']
            ?? null;

        if ($raw instanceof ElectionLifecycleStatus) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            return ElectionLifecycleStatus::from($raw);
        }

        return app(LegacyElectionPhaseResolver::class)->resolve($election);
    }

    public function advanceDue(Election $election): Election
    {
        $status = $this->currentStatus($election);
        $attributes = $election->getAttributes();
        $now = now();

        if ($status === ElectionLifecycleStatus::Scheduled) {
            $startsAt = $attributes['starts_at'] ?? null;
            if ($startsAt !== null && $now->greaterThanOrEqualTo($startsAt)) {
                return $this->transition($election, ElectionLifecycleStatus::Open, 'scheduled_start_reached', 'scheduler');
            }
        }

        if ($status === ElectionLifecycleStatus::Open) {
            $endsAt = $attributes['ends_at'] ?? null;
            if ($endsAt !== null && $now->greaterThanOrEqualTo($endsAt)) {
                return $this->transition($election, ElectionLifecycleStatus::Closed, 'voting_window_elapsed', 'scheduler');
            }
        }

        return $election;
    }
}
