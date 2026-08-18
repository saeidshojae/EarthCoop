<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupAttentionEvent;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NajmHodaGroupAttentionDeliveryService
{
    public function __construct(
        protected NajmHodaGroupAttentionEvaluatorService $evaluator
    ) {
    }

    /** @return array{sent:int,recipients:int,events:int,reason:string} */
    public function deliverGroup(Group $group): array
    {
        $setting = NajmHodaGroupAttentionSetting::query()
            ->where('group_id', $group->id)
            ->first();

        if (! $setting || ! $setting->enabled) {
            return $this->result('disabled');
        }

        $mode = strtolower(trim((string) $setting->digest_mode));
        if (! in_array($mode, ['immediate', 'daily'], true)) {
            return $this->result('delivery_off');
        }

        if ($mode === 'daily' && ! $this->dailyDigestIsDue($setting)) {
            return $this->result('daily_not_due');
        }

        $events = $mode === 'daily'
            ? $this->unresolvedEvents($group)
            : $this->evaluator->pendingNotifications($group);

        if ($events->isEmpty()) {
            return $this->result('no_pending_events');
        }

        $recipients = $this->leadershipRecipients($group);
        if ($recipients->isEmpty()) {
            return $this->result('no_recipients', events: $events->count());
        }

        $message = $this->renderDigest($group, $events);
        $eventIds = $events->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        Notification::sendNow($recipients, new GenericNotification(
            title: 'نجم هدا — نیاز به توجه مدیریتی',
            message: $message,
            url: url("/groups/{$group->id}/najm-hoda/panel"),
            type: 'warning',
            context: [
                'source' => 'najm_hoda_proactive_attention',
                'group_id' => (int) $group->id,
                'event_ids' => $eventIds,
                'digest_mode' => $mode,
            ]
        ));

        $now = now();
        NajmHodaGroupAttentionEvent::query()
            ->whereIn('id', $eventIds)
            ->update(['last_notified_at' => $now, 'updated_at' => $now]);

        $setting->forceFill(['last_digest_at' => $now])->save();

        return [
            'sent' => 1,
            'recipients' => $recipients->count(),
            'events' => $events->count(),
            'reason' => 'delivered',
        ];
    }

    protected function dailyDigestIsDue(NajmHodaGroupAttentionSetting $setting): bool
    {
        $timezone = trim((string) $setting->timezone) ?: 'UTC';
        try {
            $localNow = now()->copy()->setTimezone($timezone);
        } catch (\Throwable) {
            $timezone = 'UTC';
            $localNow = now()->copy()->setTimezone($timezone);
        }

        $preferred = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $setting->preferred_time)
            ? (string) $setting->preferred_time
            : '08:00';

        [$hour, $minute] = array_map('intval', explode(':', $preferred));
        $todayTarget = $localNow->copy()->startOfDay()->setTime($hour, $minute);

        if ($localNow->lt($todayTarget)) {
            return false;
        }

        if (! $setting->last_digest_at) {
            return true;
        }

        return ! $setting->last_digest_at
            ->copy()
            ->setTimezone($timezone)
            ->isSameDay($localNow);
    }

    /** @return Collection<int,NajmHodaGroupAttentionEvent> */
    protected function unresolvedEvents(Group $group): Collection
    {
        return NajmHodaGroupAttentionEvent::query()
            ->where('group_id', $group->id)
            ->whereNull('resolved_at')
            ->orderByRaw("FIELD(event_type,'overdue','blocked','urgent','due_soon','unassigned')")
            ->orderBy('first_seen_at')
            ->get();
    }

    /** @return Collection<int,User> */
    protected function leadershipRecipients(Group $group): Collection
    {
        $userIds = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('status', 1)
            ->whereIn('role', [2, 3])
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->where('status', 1)
            ->where('is_system', false)
            ->get();
    }

    /** @param Collection<int,NajmHodaGroupAttentionEvent> $events */
    protected function renderDigest(Group $group, Collection $events): string
    {
        $labels = [
            'overdue' => 'معوق',
            'blocked' => 'مسدود',
            'urgent' => 'فوری',
            'due_soon' => 'نزدیک موعد',
            'unassigned' => 'بدون مسئول',
        ];

        $rows = $events->groupBy('action_item_id')->map(function (Collection $itemEvents) use ($labels): array {
            /** @var NajmHodaGroupAttentionEvent $first */
            $first = $itemEvents->first();
            $payload = is_array($first->payload) ? $first->payload : [];
            $reasons = $itemEvents
                ->pluck('event_type')
                ->map(fn ($type) => $labels[(string) $type] ?? (string) $type)
                ->unique()
                ->values()
                ->all();

            return [
                'title' => trim((string) ($payload['title'] ?? 'مورد اقدام')) ?: 'مورد اقدام',
                'reasons' => $reasons,
                'assignee' => trim((string) ($payload['assignee_name'] ?? '')),
                'due_at' => $payload['due_at'] ?? null,
            ];
        })->values();

        $lines = [
            "گزارش پیگیری نجم هدا — «{$group->name}»",
            '',
            'مواردی که نیاز به توجه مدیریتی دارند:',
        ];

        foreach ($rows->take(10) as $row) {
            $parts = $row['reasons'];
            if ($row['assignee'] !== '') {
                $parts[] = 'مسئول: ' . $row['assignee'];
            }
            if (! empty($row['due_at'])) {
                $parts[] = 'موعد: ' . $row['due_at'];
            }
            $lines[] = '• ' . $row['title'] . ' — ' . implode(' | ', $parts);
        }

        if ($rows->count() > 10) {
            $lines[] = '• و ' . ($rows->count() - 10) . ' مورد دیگر در پنل نجم هدا';
        }

        $lines[] = '';
        $lines[] = 'این گزارش فقط از صف اقدام ثبت‌شده گروه ساخته شده و هیچ وضعیت یا مسئولیتی را خودکار تغییر نمی‌دهد.';

        return implode("\n", $lines);
    }

    /** @return array{sent:int,recipients:int,events:int,reason:string} */
    protected function result(string $reason, int $events = 0): array
    {
        return ['sent' => 0, 'recipients' => 0, 'events' => $events, 'reason' => $reason];
    }
}
