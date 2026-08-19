<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Alley;
use App\Models\Announcement;
use App\Models\Election;
use App\Models\ExperienceField;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\Neighborhood;
use App\Models\NotificationSetting;
use App\Models\OccupationalField;
use App\Models\Region;
use App\Models\ReportedMessage;
use App\Models\Rural;
use App\Models\Setting;
use App\Models\Street;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaOpsHealthMonitor;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Carbon\CarbonImmutable;

class FounderOperationsSnapshotService
{
    public function __construct(
        protected RuntimeEventBus $events,
        protected NajmHodaOpsHealthMonitor $healthMonitor,
        protected FounderManagedDomainRegistry $domains
    ) {
    }

    public function snapshot(int $hours = 24): array
    {
        $hours = max(1, min($hours, 168));
        $now = CarbonImmutable::now();
        $since = $now->subHours($hours);
        $next24h = $now->addHours(24);

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

        $recentManagedEvents = collect($this->events->recent(null, 500))
            ->filter(function (array $event) use ($since): bool {
                $name = (string) ($event['event'] ?? '');
                if (! $this->isManagedEvent($name)) return false;
                $timestamp = $event['timestamp'] ?? null;
                if (! is_string($timestamp) || $timestamp === '') return true;
                try { return CarbonImmutable::parse($timestamp)->greaterThanOrEqualTo($since); }
                catch (\Throwable) { return true; }
            })
            ->values()
            ->all();

        return [
            'window' => [
                'hours' => $hours,
                'since' => $since->toIso8601String(),
                'generated_at' => $now->toIso8601String(),
            ],
            'management_coverage' => $this->domains->coverage(),
            'users' => [
                'new_members' => User::query()->members()->where('created_at', '>=', $since)->count(),
                'new_verified_members' => User::query()->members()->where('created_at', '>=', $since)->whereNotNull('email_verified_at')->count(),
            ],
            'approvals' => [
                'references' => ['total' => array_sum($pendingReferences), 'by_type' => $pendingReferences],
                'locations' => ['total' => array_sum($pendingLocations), 'by_type' => $pendingLocations],
                'total' => array_sum($pendingReferences) + array_sum($pendingLocations),
            ],
            'support' => [
                'open' => Ticket::query()->where('status', 'open')->count(),
                'in_progress' => Ticket::query()->where('status', 'in-progress')->count(),
                'high_priority_active' => Ticket::query()->where('priority', 'high')->whereIn('status', ['open', 'in-progress'])->count(),
                'unassigned_active' => Ticket::query()->whereNull('assignee_id')->whereIn('status', ['open', 'in-progress'])->count(),
            ],
            'groups' => [
                'total' => Group::query()->count(),
                'open' => Group::query()->where('is_open', 1)->count(),
                'active_in_window' => Group::query()->whereNotNull('last_activity_at')->where('last_activity_at', '>=', $since)->count(),
                'created_in_window' => Group::query()->where('created_at', '>=', $since)->count(),
            ],
            'governance' => [
                'active_elections' => Election::query()->where('is_closed', 0)->count(),
                'ending_within_24h' => Election::query()->where('is_closed', 0)->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $next24h])->count(),
                'overdue_open' => Election::query()->where('is_closed', 0)->whereNotNull('ends_at')->where('ends_at', '<', $now)->count(),
                'started_in_window' => Election::query()->where('starts_at', '>=', $since)->count(),
            ],
            'moderation' => [
                'pending_group_manager' => ReportedMessage::query()->where('status', 'pending_group_manager')->count(),
                'escalated_to_admin' => ReportedMessage::query()->where(function ($query) {
                    $query->where('escalated_to_admin', 1)->orWhere('status', 'escalated_to_admin');
                })->count(),
                'unresolved_total' => ReportedMessage::query()->whereNotIn('status', ['resolved_by_group_manager', 'resolved_by_admin'])->count(),
                'created_in_window' => ReportedMessage::query()->where('created_at', '>=', $since)->count(),
            ],
            'notifications' => [
                'announcements_in_window' => Announcement::query()->where('created_at', '>=', $since)->count(),
                'pinned_announcements_in_window' => Announcement::query()->where('created_at', '>=', $since)->where('should_pin', 1)->count(),
                'preference_records' => NotificationSetting::query()->count(),
            ],
            'admin_configuration' => [
                'group_setting_records' => GroupSetting::query()->count(),
                'system_setting_records' => Setting::query()->count(),
            ],
            'runtime_health' => $this->healthMonitor->snapshot(),
            'recent_managed_events' => $recentManagedEvents,
        ];
    }

    protected function isManagedEvent(string $name): bool
    {
        if ($name === '') return false;
        foreach ($this->domains->all() as $domain) {
            foreach ((array) ($domain['event_prefixes'] ?? []) as $prefix) {
                $prefix = (string) $prefix;
                if ($prefix !== '' && str_starts_with($name, $prefix)) return true;
            }
        }
        return false;
    }
}
