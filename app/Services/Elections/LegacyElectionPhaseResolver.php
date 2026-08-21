<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class LegacyElectionPhaseResolver
{
    /**
     * Translate the coarse legacy election timestamps into the canonical
     * vocabulary without pretending we can infer post-tally states that the
     * legacy schema never persisted.
     *
     * E3 will replace this compatibility projection with a persisted state
     * machine. Until then a legacy closed election intentionally resolves only
     * to `closed`, never to awaiting_acceptance/appointing/filled.
     */
    public function resolve(Election $election, ?CarbonInterface $now = null): ElectionLifecycleStatus
    {
        $now ??= CarbonImmutable::now();

        if ((bool) $election->is_closed) {
            return ElectionLifecycleStatus::Closed;
        }

        $startsAt = $this->asImmutable($election->starts_at);
        if ($startsAt !== null && $now->lt($startsAt)) {
            return ElectionLifecycleStatus::Scheduled;
        }

        $endsAt = $this->asImmutable($election->ends_at);
        if ($endsAt !== null && $now->gte($endsAt)) {
            return ElectionLifecycleStatus::Closed;
        }

        return ElectionLifecycleStatus::Open;
    }

    private function asImmutable(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse((string) $value);
    }
}
