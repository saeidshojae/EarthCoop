<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionLifecycleTransition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ElectionLifecycleService
{
    /**
     * Canonical transition matrix. Downstream phase-specific services (E6-E8)
     * must use this state machine instead of mutating lifecycle_status directly.
     */
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

        return DB::transaction(function () use (
            $election,
            $to,
            $reason,
            $source,
            $actorUserId,
            $reference,
            $metadata,
        ): Election {
            /** @var Election $locked */
            $locked = Election::query()->lockForUpdate()->findOrFail($election->getKey());
            $from = $this->currentStatus($locked);

            // Retry-safe: a duplicate worker invocation after a successful
            // commit observes the target state and becomes a no-op.
            if ($from === $to) {
                return $locked;
            }

            if (! $this->canTransition($from, $to)) {
                throw new InvalidArgumentException(
                    "Invalid election lifecycle transition [{$from->value} -> {$to->value}]."
                );
            }

            $locked->lifecycle_status = $to;

            // Preserve legacy read compatibility. Once voting has closed, all
            // later canonical phases must remain closed to legacy UI/controllers.
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
                'transitioned_at' => now(),
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

    /**
     * Advance only transitions E3 can prove without borrowing unfinished E4-E8
     * domain rules. Tally, offers and appointments are intentionally fail-closed
     * until their dedicated services exist.
     */
    public function advanceDue(Election $election): Election
    {
        $status = $this->currentStatus($election);
        $attributes = $election->getAttributes();
        $now = now();

        if ($status === ElectionLifecycleStatus::Scheduled) {
            $startsAt = $attributes['starts_at'] ?? null;
            if ($startsAt !== null && $now->greaterThanOrEqualTo($startsAt)) {
                return $this->transition(
                    $election,
                    ElectionLifecycleStatus::Open,
                    'scheduled_start_reached',
                    'scheduler',
                );
            }
        }

        if ($status === ElectionLifecycleStatus::Open) {
            $endsAt = $attributes['ends_at'] ?? null;
            if ($endsAt !== null && $now->greaterThanOrEqualTo($endsAt)) {
                return $this->transition(
                    $election,
                    ElectionLifecycleStatus::Closed,
                    'voting_window_elapsed',
                    'scheduler',
                );
            }
        }

        return $election;
    }
}
