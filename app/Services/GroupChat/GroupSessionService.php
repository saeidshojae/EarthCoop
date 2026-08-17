<?php

namespace App\Services\GroupChat;

use App\Events\GroupFeedUpdated;
use App\Models\Group;
use App\Models\GroupSession;
use Illuminate\Support\Facades\DB;

class GroupSessionService
{
    public function activateDueForGroup(Group $group): ?GroupSession
    {
        $due = GroupSession::where('group_id', $group->id)->where('status', 'scheduled')
            ->where('starts_at', '<=', now())->oldest('starts_at')->first();
        return $due ? $this->start($due, (int) $due->created_by) : null;
    }

    public function start(GroupSession $session, int $actorId): GroupSession
    {
        [$session, $autoEndedIds] = DB::transaction(function () use ($session, $actorId) {
            $session = GroupSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->status !== 'scheduled') return [$session, []];

            $previousActive = GroupSession::where('group_id', $session->group_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($previousActive as $previous) {
                $previous->update(['status' => 'ended', 'ended_at' => now(), 'ended_by' => $actorId]);
            }

            $session->update(['status' => 'active', 'started_at' => now()]);
            $session->group()->update(['is_open' => false]);

            return [$session->fresh(), $previousActive->pluck('id')->map(fn ($id) => (int) $id)->all()];
        });

        // Starting a replacement meeting must not bypass the rule that every
        // ended official meeting receives a grounded minutes draft.
        if ($autoEndedIds !== []) {
            $actor = \App\Models\User::query()->find($actorId);
            GroupSession::query()->whereIn('id', $autoEndedIds)->get()->each(function (GroupSession $ended) use ($actor) {
                app(\App\Services\NajmHoda\NajmHodaGroupMeetingMinutesService::class)
                    ->generateDraft($ended, $actor);
            });
        }

        $this->broadcast($session, 'session_started', $actorId);
        return $session;
    }

    public function end(Group $group, int $actorId): ?GroupSession
    {
        $session = DB::transaction(function () use ($group, $actorId) {
            $session = GroupSession::where('group_id', $group->id)->where('status', 'active')->lockForUpdate()->latest('id')->first();
            if ($session) $session->update(['status' => 'ended', 'ended_at' => now(), 'ended_by' => $actorId]);
            $group->update(['is_open' => true]);
            return $session?->fresh();
        });
        if ($session) {
            $this->broadcast($session, 'session_ended', $actorId);

            // Every official session gets a grounded draft automatically. This is
            // deliberately generated after the canonical session is closed so the
            // evidence window has a stable started_at/ended_at boundary.
            $actor = \App\Models\User::query()->find($actorId);
            app(\App\Services\NajmHoda\NajmHodaGroupMeetingMinutesService::class)
                ->generateDraft($session, $actor);
        } else {
            $this->dispatch(new GroupFeedUpdated((int) $group->id, 'session_state_changed', ['is_open' => true], $actorId));
        }
        return $session;
    }

    public function payload(GroupSession $session): array
    {
        return [
            'id' => $session->id, 'is_open' => $session->status !== 'active', 'status' => $session->status,
            'title' => $session->title, 'subject' => $session->subject, 'agenda' => $session->agenda,
            'starts_at' => $session->starts_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(), 'ended_at' => $session->ended_at?->toIso8601String(),
        ];
    }

    private function broadcast(GroupSession $session, string $action, int $actorId): void
    {
        $this->dispatch(new GroupFeedUpdated((int) $session->group_id, $action, $this->payload($session), $actorId));
    }

    public function scheduled(GroupSession $session, int $actorId): void
    {
        $this->dispatch(new GroupFeedUpdated((int) $session->group_id, 'session_scheduled', $this->payload($session), $actorId));
    }

    private function dispatch(GroupFeedUpdated $event): void
    {
        app(GroupEventPublisher::class)->publish($event);
    }
}
