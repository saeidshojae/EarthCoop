<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupAttentionEvent;
use App\Models\NajmHodaGroupAttentionSetting;
use Illuminate\Support\Collection;

class NajmHodaGroupAttentionEvaluatorService
{
    /** @return array{evaluated:int,events:int,resolved:int} */
    public function evaluateGroup(Group $group): array
    {
        $setting = NajmHodaGroupAttentionSetting::query()->firstOrCreate(
            ['group_id' => $group->id],
            ['enabled' => false]
        );

        if (! $setting->enabled) {
            return ['evaluated' => 0, 'events' => 0, 'resolved' => 0];
        }

        $now = now();
        $dueSoon = $now->copy()->addHours(max(1, (int) $setting->due_soon_hours));
        $active = NajmHodaGroupActionItem::query()
            ->where('group_id', $group->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get();

        $seenFingerprints = [];
        $eventCount = 0;

        foreach ($active as $item) {
            foreach ($this->classify($item, $setting, $now, $dueSoon) as $type => $payload) {
                $fingerprint = hash('sha256', $group->id . '|' . $item->id . '|' . $type);
                $seenFingerprints[] = $fingerprint;

                $event = NajmHodaGroupAttentionEvent::query()->firstOrNew(['fingerprint' => $fingerprint]);
                $isNew = ! $event->exists;
                $event->fill([
                    'group_id' => $group->id,
                    'action_item_id' => $item->id,
                    'event_type' => $type,
                    'last_seen_at' => $now,
                    'resolved_at' => null,
                    'payload' => $payload,
                ]);
                if ($isNew) {
                    $event->first_seen_at = $now;
                    $event->occurrences = 1;
                } else {
                    $event->occurrences = ((int) $event->occurrences) + 1;
                }
                $event->save();
                $eventCount++;
            }
        }

        $resolveQuery = NajmHodaGroupAttentionEvent::query()
            ->where('group_id', $group->id)
            ->whereNull('resolved_at');
        if ($seenFingerprints !== []) {
            $resolveQuery->whereNotIn('fingerprint', $seenFingerprints);
        }
        $resolved = $resolveQuery->update(['resolved_at' => $now, 'updated_at' => $now]);

        $setting->forceFill(['last_evaluated_at' => $now])->save();

        return ['evaluated' => $active->count(), 'events' => $eventCount, 'resolved' => $resolved];
    }

    /** @return array<string,array<string,mixed>> */
    protected function classify(NajmHodaGroupActionItem $item, NajmHodaGroupAttentionSetting $setting, $now, $dueSoon): array
    {
        $events = [];
        $base = [
            'title' => (string) $item->title,
            'priority' => (string) $item->priority,
            'status' => (string) $item->status,
            'due_at' => $item->due_at?->toIso8601String(),
            'assignee_name' => $item->assignee_name,
        ];

        if ($setting->alert_overdue && $item->due_at && $item->due_at->lt($now)) {
            $events['overdue'] = $base;
        }
        if ($setting->alert_blocked && (string) $item->status === 'blocked') {
            $events['blocked'] = $base;
        }
        if ($setting->alert_urgent && (string) $item->priority === 'urgent') {
            $events['urgent'] = $base;
        }
        if ($setting->alert_due_soon && $item->due_at && $item->due_at->gte($now) && $item->due_at->lte($dueSoon)) {
            $events['due_soon'] = $base;
        }
        if ($setting->alert_unassigned && ! $item->assigned_user_id && trim((string) $item->assignee_name) === '') {
            $events['unassigned'] = $base;
        }

        return $events;
    }

    /** @return Collection<int,NajmHodaGroupAttentionEvent> */
    public function pendingNotifications(Group $group): Collection
    {
        $setting = NajmHodaGroupAttentionSetting::query()->where('group_id', $group->id)->first();
        if (! $setting || ! $setting->enabled) return collect();

        $cutoff = now()->subMinutes(max(1, (int) $setting->suppress_minutes));

        return NajmHodaGroupAttentionEvent::query()
            ->where('group_id', $group->id)
            ->whereNull('resolved_at')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_notified_at')->orWhere('last_notified_at', '<=', $cutoff);
            })
            ->orderByRaw("FIELD(event_type,'overdue','blocked','urgent','due_soon','unassigned')")
            ->orderBy('first_seen_at')
            ->get();
    }
}
