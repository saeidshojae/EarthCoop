<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Carbon\CarbonImmutable;

class FounderAcceptanceStatusService
{
    public function __construct(protected RuntimeEventBus $events) {}

    /** @return array<string,mixed> */
    public function snapshot(int $hours = 24, int $limit = 50): array
    {
        $hours = max(1, min($hours, 168));
        $limit = max(1, min($limit, 200));
        $cutoff = now()->subHours($hours);

        $executions = collect($this->events->recent('najm_hoda.founder_ops.execution.completed', max($limit * 4, 100)))
            ->filter(function (array $event) use ($cutoff): bool {
                $timestamp = $event['timestamp'] ?? null;
                if (! is_string($timestamp) || $timestamp === '') return true;
                try {
                    return CarbonImmutable::parse($timestamp)->greaterThanOrEqualTo($cutoff);
                } catch (\Throwable) {
                    return true;
                }
            })
            ->take($limit)
            ->map(function (array $event): array {
                $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
                $verified = (bool) ($payload['outcome_verified'] ?? false);
                $verificationStatus = (string) ($payload['verification_status'] ?? 'unknown');

                $acceptance = $verified
                    ? 'verified'
                    : ($verificationStatus === 'not_configured' ? 'verification_pending' : 'needs_review');

                return [
                    'domain' => (string) ($payload['domain'] ?? ''),
                    'action' => (string) ($payload['action'] ?? ''),
                    'mode' => (string) ($payload['mode'] ?? ''),
                    'approval_request_id' => $payload['approval_request_id'] ?? null,
                    'acceptance' => $acceptance,
                    'verification_status' => $verificationStatus,
                    'outcome_verified' => $verified,
                    'context' => is_array($payload['context'] ?? null) ? $payload['context'] : [],
                    'timestamp' => $event['timestamp'] ?? null,
                ];
            })
            ->values();

        return [
            'window_hours' => $hours,
            'counts' => [
                'executed' => $executions->count(),
                'verified' => $executions->where('acceptance', 'verified')->count(),
                'verification_pending' => $executions->where('acceptance', 'verification_pending')->count(),
                'needs_review' => $executions->where('acceptance', 'needs_review')->count(),
            ],
            'items' => $executions->all(),
        ];
    }
}
