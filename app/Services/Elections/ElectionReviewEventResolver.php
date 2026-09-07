<?php

namespace App\Services\Elections;

use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\ElectionLifecycleTransition;
use App\Models\ElectionRepresentationAssignment;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\ElectionVoteSnapshotRun;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/** Resolves reviewable user-facing events only from persisted election evidence. */
class ElectionReviewEventResolver
{
    public const TYPES = [
        'eligibility_snapshot',
        'ballot_event',
        'vote_snapshot',
        'tally_result',
        'responsibility_offer',
        'appointment',
        'representation_assignment',
        'lifecycle_transition',
    ];

    /** @return array{occurred_at:CarbonInterface,subject_user_id:?int,appointment_id:?int} */
    public function resolve(Election $election, string $type, int $id): array
    {
        if (! in_array($type, self::TYPES, true) || $id <= 0) {
            throw new InvalidArgumentException('Unsupported or invalid election review event reference.');
        }

        return match ($type) {
            'eligibility_snapshot' => $this->eligibility($election, $id),
            'ballot_event' => $this->ballot($election, $id),
            'vote_snapshot' => $this->voteSnapshot($election, $id),
            'tally_result' => $this->tally($election, $id),
            'responsibility_offer' => $this->offer($election, $id),
            'appointment' => $this->appointment($election, $id),
            'representation_assignment' => $this->representation($election, $id),
            'lifecycle_transition' => $this->transition($election, $id),
        };
    }

    private function eligibility(Election $election, int $id): array
    {
        $row = ElectionEligibilitySnapshot::query()->findOrFail($id);
        $this->sameElection($election, (int) $row->election_id);
        return $this->payload($row->captured_at, (int) $row->user_id);
    }

    private function ballot(Election $election, int $id): array
    {
        $row = ElectionBallotEvent::query()->findOrFail($id);
        $this->sameElection($election, (int) $row->election_id);
        return $this->payload($row->occurred_at, $row->candidate_user_id ? (int) $row->candidate_user_id : ($row->previous_candidate_user_id ? (int) $row->previous_candidate_user_id : null));
    }

    private function voteSnapshot(Election $election, int $id): array
    {
        $row = ElectionVoteSnapshotRun::query()->findOrFail($id);
        $this->sameElection($election, (int) $row->election_id);
        return $this->payload($row->stopped_at);
    }

    private function tally(Election $election, int $id): array
    {
        $row = ElectionTallyResult::query()->findOrFail($id);
        $this->sameElection($election, (int) $row->election_id);
        return $this->payload($row->tallied_at, (int) $row->candidate_user_id);
    }

    private function offer(Election $election, int $id): array
    {
        $row = ElectionResponsibilityOffer::query()->findOrFail($id);
        $this->sameElection($election, (int) $row->election_id);
        return $this->payload($row->responded_at ?? $row->offered_at, (int) $row->candidate_user_id);
    }

    private function appointment(Election $election, int $id): array
    {
        $row = ElectionAppointment::query()->findOrFail($id);
        $this->sameElection($election, (int) $row->election_id);
        return $this->payload($row->appointed_at, (int) $row->user_id, (int) $row->id);
    }

    private function representation(Election $election, int $id): array
    {
        $row = ElectionRepresentationAssignment::query()->with('appointment')->findOrFail($id);
        if (! $row->appointment) {
            throw new InvalidArgumentException('Representation assignment is missing its election appointment evidence.');
        }
        $this->sameElection($election, (int) $row->appointment->election_id);
        return $this->payload($row->activated_at, (int) $row->user_id, (int) $row->appointment_id);
    }

    private function transition(Election $election, int $id): array
    {
        $row = ElectionLifecycleTransition::query()->findOrFail($id);
        $this->sameElection($election, (int) $row->election_id);
        return $this->payload($row->transitioned_at);
    }

    private function payload(?CarbonInterface $occurredAt, ?int $subjectUserId = null, ?int $appointmentId = null): array
    {
        if ($occurredAt === null) {
            throw new InvalidArgumentException('Referenced election event does not have a canonical occurrence time.');
        }
        return ['occurred_at' => $occurredAt, 'subject_user_id' => $subjectUserId, 'appointment_id' => $appointmentId];
    }

    private function sameElection(Election $election, int $electionId): void
    {
        if ((int) $election->id !== $electionId) {
            throw new InvalidArgumentException('Referenced event does not belong to the challenged election.');
        }
    }
}
