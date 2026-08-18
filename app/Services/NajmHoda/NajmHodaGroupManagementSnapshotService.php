<?php

namespace App\Services\NajmHoda;

use App\Models\ChatRequest;
use App\Models\Group;
use App\Models\GroupSession;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;

class NajmHodaGroupManagementSnapshotService
{
    /** @return array<string,mixed> */
    public function snapshot(Group $group, int $role): array
    {
        $sessions = GroupSession::query()->where('group_id', $group->id);
        $activeSession = (clone $sessions)->where('status', 'active')->latest('id')->first();
        $nextSession = (clone $sessions)->where('status', 'scheduled')->oldest('starts_at')->first();

        $draftMinutes = NajmHodaGroupMeetingMinute::query()
            ->where('group_id', $group->id)
            ->where('status', 'draft')
            ->latest('id')
            ->get();

        $pendingDecisions = $draftMinutes->sum(function (NajmHodaGroupMeetingMinute $minute): int {
            return collect($minute->decision_candidates ?? [])
                ->filter(fn ($candidate) => is_array($candidate) && ($candidate['state'] ?? 'candidate') === 'candidate')
                ->count();
        });

        $actions = NajmHodaGroupActionItem::query()->where('group_id', $group->id);
        $activeActions = (clone $actions)->whereIn('status', ['open', 'in_progress', 'blocked'])->count();

        return [
            'sessions' => [
                'active_count' => $activeSession ? 1 : 0,
                'scheduled_count' => (clone $sessions)->where('status', 'scheduled')->count(),
                'active' => $activeSession ? [
                    'id' => (int) $activeSession->id,
                    'title' => (string) $activeSession->title,
                    'started_at' => optional($activeSession->started_at)->toIso8601String(),
                ] : null,
                'next' => $nextSession ? [
                    'id' => (int) $nextSession->id,
                    'title' => (string) $nextSession->title,
                    'starts_at' => optional($nextSession->starts_at)->toIso8601String(),
                ] : null,
            ],
            'minutes' => [
                'draft_count' => $draftMinutes->count(),
                'pending_decisions' => (int) $pendingDecisions,
            ],
            'actions' => [
                'active_count' => (int) $activeActions,
                'open' => (clone $actions)->where('status', 'open')->count(),
                'in_progress' => (clone $actions)->where('status', 'in_progress')->count(),
                'blocked' => (clone $actions)->where('status', 'blocked')->count(),
                'done' => (clone $actions)->where('status', 'done')->count(),
            ],
            'requests' => [
                'pending_group_chat' => $role === 3
                    ? ChatRequest::query()
                        ->where('request_to_group', $group->id)
                        ->where('status', 'pending')
                        ->count()
                    : 0,
            ],
        ];
    }
}
