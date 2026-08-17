<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\NajmHodaGroupAttentionEvent;
use App\Models\NajmHodaGroupAttentionSetting;

class NajmHodaGroupAttentionPanelService
{
    public function snapshot(Group $group): array
    {
        $setting = NajmHodaGroupAttentionSetting::firstOrCreate(
            ['group_id' => $group->id],
            [
                'enabled' => false,
                'due_soon_hours' => 48,
                'suppress_minutes' => 720,
                'alert_overdue' => true,
                'alert_due_soon' => true,
                'alert_blocked' => true,
                'alert_urgent' => true,
                'alert_unassigned' => true,
                'digest_mode' => 'daily',
                'timezone' => (string) config('app.timezone', 'UTC'),
                'preferred_time' => '08:00',
            ]
        );

        $active = NajmHodaGroupAttentionEvent::query()
            ->where('group_id', $group->id)
            ->whereNull('resolved_at')
            ->with('actionItem:id,title,status,priority,assignee_name,due_at')
            ->latest('last_seen_at')
            ->get();

        $typeCounts = $active->countBy('event_type');

        return [
            'policy' => [
                'enabled' => (bool) $setting->enabled,
                'digest_mode' => (string) $setting->digest_mode,
                'preferred_time' => (string) $setting->preferred_time,
                'timezone' => (string) $setting->timezone,
                'due_soon_hours' => (int) $setting->due_soon_hours,
                'suppress_minutes' => (int) $setting->suppress_minutes,
                'alert_overdue' => (bool) $setting->alert_overdue,
                'alert_due_soon' => (bool) $setting->alert_due_soon,
                'alert_blocked' => (bool) $setting->alert_blocked,
                'alert_urgent' => (bool) $setting->alert_urgent,
                'alert_unassigned' => (bool) $setting->alert_unassigned,
                'last_evaluated_at' => optional($setting->last_evaluated_at)->toIso8601String(),
                'last_digest_at' => optional($setting->last_digest_at)->toIso8601String(),
            ],
            'stats' => [
                'active_events' => $active->count(),
                'overdue' => (int) ($typeCounts['overdue'] ?? 0),
                'due_soon' => (int) ($typeCounts['due_soon'] ?? 0),
                'blocked' => (int) ($typeCounts['blocked'] ?? 0),
                'urgent' => (int) ($typeCounts['urgent'] ?? 0),
                'unassigned' => (int) ($typeCounts['unassigned'] ?? 0),
            ],
            'events' => $active->map(fn ($event) => [
                'id' => $event->id,
                'action_item_id' => $event->action_item_id,
                'event_type' => $event->event_type,
                'occurrences' => (int) $event->occurrences,
                'first_seen_at' => optional($event->first_seen_at)->toIso8601String(),
                'last_seen_at' => optional($event->last_seen_at)->toIso8601String(),
                'last_notified_at' => optional($event->last_notified_at)->toIso8601String(),
                'action_item' => $event->actionItem ? [
                    'title' => $event->actionItem->title,
                    'status' => $event->actionItem->status,
                    'priority' => $event->actionItem->priority,
                    'assignee_name' => $event->actionItem->assignee_name,
                    'due_at' => optional($event->actionItem->due_at)->toIso8601String(),
                ] : null,
            ])->values()->all(),
        ];
    }

    public function updatePolicy(Group $group, array $input): NajmHodaGroupAttentionSetting
    {
        $allowed = [
            'enabled','digest_mode','preferred_time','timezone','due_soon_hours','suppress_minutes',
            'alert_overdue','alert_due_soon','alert_blocked','alert_urgent','alert_unassigned',
        ];

        $setting = NajmHodaGroupAttentionSetting::firstOrCreate(['group_id' => $group->id]);
        $setting->fill(array_intersect_key($input, array_flip($allowed)));
        $setting->save();

        return $setting->fresh();
    }
}
