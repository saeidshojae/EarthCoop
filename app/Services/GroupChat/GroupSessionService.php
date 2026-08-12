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
        return DB::transaction(function () use ($session, $actorId) {
            $session = GroupSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->status !== 'scheduled') return $session;
            GroupSession::where('group_id', $session->group_id)->where('status', 'active')
                ->update(['status' => 'ended', 'ended_at' => now(), 'ended_by' => $actorId]);
            $session->update(['status' => 'active', 'started_at' => now()]);
            $session->group()->update(['is_open' => false]);
            $this->broadcast($session, 'session_started', $actorId);
            return $session->fresh();
        });
    }

    public function end(Group $group, int $actorId): ?GroupSession
    {
        return DB::transaction(function () use ($group, $actorId) {
            $session = GroupSession::where('group_id', $group->id)->where('status', 'active')->lockForUpdate()->latest('id')->first();
            if ($session) $session->update(['status' => 'ended', 'ended_at' => now(), 'ended_by' => $actorId]);
            $group->update(['is_open' => true]);
            if ($session) $this->broadcast($session, 'session_ended', $actorId);
            else event(new GroupFeedUpdated((int) $group->id, 'session_state_changed', ['is_open' => true], $actorId));
            return $session?->fresh();
        });
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
        event(new GroupFeedUpdated((int) $session->group_id, $action, $this->payload($session), $actorId));
    }
}
