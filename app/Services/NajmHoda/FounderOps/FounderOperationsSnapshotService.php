<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Alley;
use App\Models\ExperienceField;
use App\Models\Neighborhood;
use App\Models\OccupationalField;
use App\Models\Region;
use App\Models\Rural;
use App\Models\Street;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Carbon\CarbonImmutable;

class FounderOperationsSnapshotService
{
    public function __construct(
        protected RuntimeEventBus $events
    ) {
    }

    public function snapshot(int $hours = 24): array
    {
        $hours = max(1, min($hours, 168));
        $since = CarbonImmutable::now()->subHours($hours);

        $pendingLocations = [
            'alley' => Alley::query()->where('status', 0)->count(),
            'street' => Street::query()->where('status', 0)->count(),
            'neighborhood' => Neighborhood::query()->where('status', 0)->count(),
            'region' => Region::query()->where('status', 0)->count(),
            'rural' => Rural::query()->where('status', 0)->count(),
        ];

        $pendingReferences = [
            'experience' => ExperienceField::query()->where('status', 0)->count(),
            'occupational' => OccupationalField::query()->where('status', 0)->count(),
        ];

        $recentFounderEvents = collect($this->events->recent(null, 200))
            ->filter(function (array $event) use ($since): bool {
                $name = (string) ($event['event'] ?? '');
                if (! str_starts_with($name, 'najm_hoda.input.founder.')) {
                    return false;
                }

                $timestamp = $event['timestamp'] ?? null;
                if (! is_string($timestamp) || $timestamp === '') {
                    return true;
                }

                try {
                    return CarbonImmutable::parse($timestamp)->greaterThanOrEqualTo($since);
                } catch (\Throwable) {
                    return true;
                }
            })
            ->values()
            ->all();

        return [
            'window' => [
                'hours' => $hours,
                'since' => $since->toIso8601String(),
                'generated_at' => CarbonImmutable::now()->toIso8601String(),
            ],
            'users' => [
                'new_members' => User::query()
                    ->members()
                    ->where('created_at', '>=', $since)
                    ->count(),
                'new_verified_members' => User::query()
                    ->members()
                    ->where('created_at', '>=', $since)
                    ->whereNotNull('email_verified_at')
                    ->count(),
            ],
            'approvals' => [
                'references' => [
                    'total' => array_sum($pendingReferences),
                    'by_type' => $pendingReferences,
                ],
                'locations' => [
                    'total' => array_sum($pendingLocations),
                    'by_type' => $pendingLocations,
                ],
                'total' => array_sum($pendingReferences) + array_sum($pendingLocations),
            ],
            'support' => [
                'open' => Ticket::query()->where('status', 'open')->count(),
                'in_progress' => Ticket::query()->where('status', 'in-progress')->count(),
                'high_priority_active' => Ticket::query()
                    ->where('priority', 'high')
                    ->whereIn('status', ['open', 'in-progress'])
                    ->count(),
                'unassigned_active' => Ticket::query()
                    ->whereNull('assignee_id')
                    ->whereIn('status', ['open', 'in-progress'])
                    ->count(),
            ],
            'recent_founder_events' => $recentFounderEvents,
        ];
    }
}
