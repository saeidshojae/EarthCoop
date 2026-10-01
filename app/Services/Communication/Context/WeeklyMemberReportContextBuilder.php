<?php

namespace App\Services\Communication\Context;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\Poll;
use App\Models\User;
use App\Services\Groups\EffectiveGroupMembershipService;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Carbon\CarbonPeriod;

final class WeeklyMemberReportContextBuilder
{
    public function __construct(
        private readonly EffectiveGroupMembershipService $memberships,
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
    ) {
    }

    /** @return array<string,mixed> */
    public function build(User $user, CarbonPeriod $period): array
    {
        $groupIds = $this->memberships
            ->currentForUser($user)
            ->pluck('group_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $context = $this->temporalContexts->forRecipient($user);

        return [
            'period_start' => $this->temporal->date($period->getStartDate(), $context, 'short'),
            'period_end' => $this->temporal->date($period->getEndDate(), $context, 'short'),
            'display_name' => $this->displayName($user),
            'groups_count' => count($groupIds),
            'open_elections_count' => $this->openElectionsCount($groupIds),
            'open_polls_count' => $this->openPollsCount($groupIds),
            'unread_notifications_count' => $user->unreadNotifications()->count(),
        ];
    }

    /** @param array<int,int> $groupIds */
    public function openElectionsCount(array $groupIds): int
    {
        if ($groupIds === []) {
            return 0;
        }

        return Election::query()
            ->whereIn('group_id', $groupIds)
            ->where('lifecycle_status', ElectionLifecycleStatus::Open->value)
            ->where('is_closed', false)
            ->count();
    }

    /** @param array<int,int> $groupIds */
    public function openPollsCount(array $groupIds): int
    {
        if ($groupIds === []) {
            return 0;
        }

        return Poll::query()
            ->whereIn('group_id', $groupIds)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    private function displayName(User $user): string
    {
        $name = trim(implode(' ', array_filter([
            trim((string) ($user->first_name ?? '')),
            trim((string) ($user->last_name ?? '')),
        ], fn (string $part): bool => $part !== '')));

        return $name !== '' ? $name : trim((string) ($user->email ?? ''));
    }
}
