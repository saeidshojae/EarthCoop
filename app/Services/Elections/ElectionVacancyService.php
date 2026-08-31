<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionPosition;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\ElectionVacancy;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ElectionVacancyService
{
    public function __construct(
        private readonly ElectionAppointmentService $appointments,
        private readonly ElectionPolicyResolver $policyResolver,
    ) {}

    /**
     * Open a planned succession without ending the incumbent appointment.
     * The incumbent remains responsible until a replacement appointment has
     * been successfully installed. Hard invalidation continues to use revoke().
     */
    public function openPlannedSuccession(
        ElectionAppointment $appointment,
        string $reason,
        string $actor = 'election_vacancy_service',
    ): ElectionVacancy {
        if (trim($reason) === '' || trim($actor) === '') {
            throw new InvalidArgumentException('Planned succession requires actor and reason.');
        }

        return DB::transaction(function () use ($appointment, $reason, $actor): ElectionVacancy {
            $locked = ElectionAppointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($locked->appointment_kind !== 'direct' || $locked->status !== 'active') {
                throw new RuntimeException('Planned succession requires an active direct appointment.');
            }

            return ElectionVacancy::query()->firstOrCreate(
                ['source_appointment_id' => $locked->id],
                [
                    'election_id' => $locked->election_id,
                    'user_id' => $locked->user_id,
                    'group_id' => $locked->group_id,
                    'position' => $locked->position,
                    'continuity_mode' => 'planned',
                    'status' => 'open',
                    'opened_at' => now(),
                    'actor' => trim($actor),
                    'reason' => trim($reason),
                    'metadata' => [
                        'incumbent_remains_active_until_replacement' => true,
                        'source_responsibility_offer_id' => (int) $locked->responsibility_offer_id,
                    ],
                ],
            );
        }, 3);
    }

    public function processDue(int $limit = 100): array
    {
        $ids = ElectionVacancy::query()
            ->whereIn('status', ['open', 'offer_pending'])
            ->orderBy('opened_at')
            ->limit(max(1, $limit))
            ->pluck('id');

        $processed = 0;
        $filled = 0;
        $exhausted = 0;

        foreach ($ids as $id) {
            $result = $this->processOne((int) $id);
            $processed++;
            $filled += $result === 'filled' ? 1 : 0;
            $exhausted += $result === 'exhausted' ? 1 : 0;
        }

        return compact('processed', 'filled', 'exhausted');
    }

    public function processOne(int $vacancyId): string
    {
        return DB::transaction(function () use ($vacancyId): string {
            $vacancy = ElectionVacancy::query()->lockForUpdate()->findOrFail($vacancyId);
            if (! in_array($vacancy->status, ['open', 'offer_pending'], true)) {
                return $vacancy->status;
            }

            if ($vacancy->replacement_offer_id !== null) {
                $offer = ElectionResponsibilityOffer::query()->lockForUpdate()->find($vacancy->replacement_offer_id);
                if ($offer !== null) {
                    if ($offer->status === ElectionResponsibilityOfferStatus::Pending) {
                        return 'offer_pending';
                    }

                    if ($offer->status === ElectionResponsibilityOfferStatus::Accepted) {
                        $appointment = $this->appointments->appoint($offer);

                        if ($vacancy->continuity_mode === 'planned') {
                            $source = ElectionAppointment::query()->find($vacancy->source_appointment_id);
                            if ($source !== null && $source->status === 'active') {
                                // Install replacement first, then retire incumbent.
                                $this->appointments->revoke(
                                    $source,
                                    'planned_succession_replacement_appointed',
                                    'election_vacancy_service',
                                );
                            }
                        }

                        $vacancy->forceFill([
                            'status' => 'filled',
                            'resolved_at' => now(),
                            'replacement_appointment_id' => $appointment->id,
                        ])->save();
                        return 'filled';
                    }
                }

                $vacancy->forceFill([
                    'status' => 'open',
                    'replacement_offer_id' => null,
                ])->save();
            }

            $position = ElectionPosition::from($vacancy->position);
            $election = Election::query()->findOrFail($vacancy->election_id);
            $policy = $this->policyForElection($election);
            $contract = $this->contractForElection($position, $election, $policy);
            $responseDays = $this->policyResolver->responseDurationDays($policy);

            $alreadyOffered = ElectionResponsibilityOffer::query()
                ->where('election_id', $vacancy->election_id)
                ->where('position', $position->value)
                ->pluck('candidate_user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $ranked = ElectionTallyResult::query()
                ->where('election_id', $vacancy->election_id)
                ->where('position', $position->value)
                ->orderBy('rank')
                ->get();

            foreach ($ranked as $row) {
                $candidateUserId = (int) $row->candidate_user_id;
                if (in_array($candidateUserId, $alreadyOffered, true)) {
                    continue;
                }

                $eligible = $this->isCurrentlyEligible((int) $vacancy->group_id, $candidateUserId);
                $offer = ElectionResponsibilityOffer::create([
                    'election_id' => $vacancy->election_id,
                    'candidate_user_id' => $candidateUserId,
                    'position' => $position->value,
                    'ranking_position' => (int) $row->rank,
                    'contract_version_id' => $contract->id,
                    'status' => $eligible
                        ? ElectionResponsibilityOfferStatus::Pending
                        : ElectionResponsibilityOfferStatus::Ineligible,
                    'offered_at' => now(),
                    'expires_at' => $eligible ? now()->addDays($responseDays) : now(),
                    'responded_at' => $eligible ? null : now(),
                    'eligibility_checked_at' => now(),
                    'resolution_reason' => $eligible ? 'post_appointment_vacancy_ranked_backfill' : 'candidate_ineligible_for_post_appointment_vacancy',
                    'response_metadata' => [
                        'vacancy_id' => (int) $vacancy->id,
                        'source_appointment_id' => (int) $vacancy->source_appointment_id,
                        'continuity_mode' => $vacancy->continuity_mode,
                        'policy_version_id' => $election->policy_version_id,
                        'response_duration_days' => $responseDays,
                        'contract_version_id' => (int) $contract->id,
                        'contract_frozen_by_policy' => $policy instanceof ElectionPolicyVersion,
                    ],
                ]);

                $alreadyOffered[] = $candidateUserId;
                if (! $eligible) {
                    continue;
                }

                $vacancy->forceFill([
                    'status' => 'offer_pending',
                    'replacement_offer_id' => $offer->id,
                ])->save();

                return 'offer_pending';
            }

            $vacancy->forceFill([
                'status' => 'exhausted',
                'resolved_at' => now(),
            ])->save();

            return 'exhausted';
        }, 3);
    }

    private function policyForElection(Election $election): object
    {
        try {
            return $this->policyResolver->resolveForElection($election);
        } catch (RuntimeException) {
            return $this->policyResolver->resolveForGroup($election->group);
        }
    }

    private function contractForElection(
        ElectionPosition $position,
        Election $election,
        object $policy,
    ): ElectionResponsibilityContractVersion {
        if ($election->policy_version_id !== null) {
            if (! $policy instanceof ElectionPolicyVersion) {
                throw new RuntimeException("Election [{$election->id}] has a policy version id but no canonical policy version could be resolved.");
            }

            $frozenId = $position === ElectionPosition::Manager
                ? $policy->manager_contract_version_id
                : $policy->inspector_contract_version_id;

            if ($frozenId === null) {
                throw new RuntimeException("Election [{$election->id}] policy version [{$policy->id}] has no frozen responsibility contract for [{$position->value}].");
            }

            $contract = ElectionResponsibilityContractVersion::query()->find((int) $frozenId);
            if ($contract === null || $contract->position !== $position->value || $contract->published_at === null) {
                throw new RuntimeException("Frozen responsibility contract [{$frozenId}] is invalid for [{$position->value}].");
            }

            return $contract;
        }

        $contract = ElectionResponsibilityContractVersion::query()
            ->where('position', $position->value)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();

        if ($contract === null) {
            throw new RuntimeException("No published active responsibility contract exists for [{$position->value}].");
        }

        return $contract;
    }

    private function isCurrentlyEligible(int $groupId, int $candidateUserId): bool
    {
        $user = User::query()->find($candidateUserId);
        if ($user === null || (bool) $user->is_system) {
            return false;
        }

        $membership = GroupUser::query()
            ->where('group_id', $groupId)
            ->where('user_id', $candidateUserId)
            ->first();

        if ($membership === null || (int) $membership->status !== 1) {
            return false;
        }

        $role = (int) $membership->role;
        return $role >= 1 && $role !== 4;
    }
}
