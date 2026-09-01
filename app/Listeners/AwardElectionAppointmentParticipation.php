<?php

namespace App\Listeners;

use App\Events\Elections\ElectionAppointmentApplied;
use App\Services\ReputationService;
use Illuminate\Support\Facades\Log;

class AwardElectionAppointmentParticipation
{
    public function __construct(private readonly ReputationService $reputation)
    {
    }

    public function handle(ElectionAppointmentApplied $event): void
    {
        $appointment = $event->appointment->fresh(['user', 'group']);
        if (! $appointment || $appointment->appointment_kind !== 'direct' || $appointment->status !== 'active') {
            return;
        }

        $user = $appointment->user;
        $group = $appointment->group;
        $position = (string) $appointment->position;

        if (! $user || ! $group || ! in_array($position, ['manager', 'inspector'], true)) {
            return;
        }

        $action = 'elected_' . $position;
        $eventKey = $action . ':user:' . $user->id . ':level:' . $group->location_level;

        try {
            $this->reputation->applyAction(
                $user,
                $action,
                [
                    'appointment_id' => (int) $appointment->id,
                    'election_id' => (int) $appointment->election_id,
                    'group_id' => (int) $group->id,
                    'position' => $position,
                    'governance_level' => (string) $group->location_level,
                ],
                $appointment->id,
                'elections.appointment',
                $eventKey
            );
        } catch (\Throwable $exception) {
            Log::warning('election_appointment_reputation_failed', [
                'appointment_id' => (int) $appointment->id,
                'user_id' => (int) $user->id,
                'position' => $position,
                'governance_level' => (string) $group->location_level,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
