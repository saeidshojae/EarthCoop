<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionAcceptanceStatus;
use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ElectionResponsibilityOfferService
{
    public const RESPONSE_WINDOW_DAYS = 7;

    public function __construct(
        private readonly ElectionLifecycleService $lifecycle,
        private readonly ElectionPolicyResolver $policyResolver,
    ) {}

    public function start(Election $election): array
    {
        return DB::transaction(function () use ($election): array {
            $locked = Election::query()->lockForUpdate()->findOrFail($election->id);
            if ($this->lifecycle->currentStatus($locked) !== ElectionLifecycleStatus::Tallying) {
                throw new RuntimeException('Election is not ready to start responsibility offers.');
            }

            foreach (ElectionPosition::cases() as $position) {
                $this->fillOpenSlots($locked, $position);
            }

            $locked = $this->lifecycle->transition(
                $locked,
                ElectionLifecycleStatus::AwaitingAcceptance,
                'responsibility_offers_started',
                'election_responsibility_offer_service',
            );
            $locked = $this->reconcileExhaustion($locked);

            return $this->summary($locked);
        }, 3);
    }

    public function accept(ElectionResponsibilityOffer $offer, int $candidateUserId): ElectionResponsibilityOffer
    {
        $result = DB::transaction(function () use ($offer, $candidateUserId): array {
            $locked = ElectionResponsibilityOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $election = Election::query()->lockForUpdate()->findOrFail($locked->election_id);
            $this->assertPendingForCandidate($locked, $candidateUserId);

            if ($locked->expires_at->isPast()) {
                $this->resolve($locked, ElectionResponsibilityOfferStatus::Expired, 'response_deadline_elapsed');
                $this->fillOpenSlots($election, ElectionPosition::from($locked->position));
                $this->reconcileExhaustion($election->refresh());

                return [
                    'offer' => $locked->refresh(),
                    'error' => 'مهلت پذیرش این دعوت پایان یافته است.',
                ];
            }

            if (! $this->isCurrentlyEligible($election, $candidateUserId)) {
                $this->resolve($locked, ElectionResponsibilityOfferStatus::Ineligible, 'candidate_no_longer_eligible');
                $this->fillOpenSlots($election, ElectionPosition::from($locked->position));
                $this->reconcileExhaustion($election->refresh());

                return [
                    'offer' => $locked->refresh(),
                    'error' => 'شرایط عضویت فعال برای پذیرش این مسئولیت برقرار نیست.',
                ];
            }

            $locked->forceFill([
                'status' => ElectionResponsibilityOfferStatus::Accepted,
                'responded_at' => now(),
                'eligibility_checked_at' => now(),
                'resolution_reason' => 'candidate_accepted_contract',
                'response_metadata' => array_merge($locked->response_metadata ?? [], [
                    'candidate_user_id' => $candidateUserId,
                    'contract_version_id' => (int) $locked->contract_version_id,
                ]),
            ])->save();
            $this->syncCandidateProjection($locked);

            return ['offer' => $locked->refresh(), 'error' => null];
        }, 3);

        if ($result['error'] !== null) {
            throw ValidationException::withMessages(['offer' => $result['error']]);
        }

        return $result['offer'];
    }

    public function decline(ElectionResponsibilityOffer $offer, int $candidateUserId): ElectionResponsibilityOffer
    {
        return DB::transaction(function () use ($offer, $candidateUserId): ElectionResponsibilityOffer {
            $locked = ElectionResponsibilityOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $election = Election::query()->lockForUpdate()->findOrFail($locked->election_id);
            $this->assertPendingForCandidate($locked, $candidateUserId);

            $this->resolve($locked, ElectionResponsibilityOfferStatus::Declined, 'candidate_declined_contract');
            $this->fillOpenSlots($election, ElectionPosition::from($locked->position));
            $this->reconcileExhaustion($election->refresh());

            return $locked->refresh();
        }, 3);
    }

    public function expireDue(int $limit = 100): int
    {
        $ids = ElectionResponsibilityOffer::query()
            ->where('status', ElectionResponsibilityOfferStatus::Pending->value)
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->pluck('id');

        $processed = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$processed): void {
                $offer = ElectionResponsibilityOffer::query()->lockForUpdate()->find($id);
                if ($offer === null || $offer->status !== ElectionResponsibilityOfferStatus::Pending || $offer->expires_at->isFuture()) {
                    return;
                }

                $election = Election::query()->lockForUpdate()->findOrFail($offer->election_id);
                $this->resolve($offer, ElectionResponsibilityOfferStatus::Expired, 'response_deadline_elapsed');
                $this->fillOpenSlots($election, ElectionPosition::from($offer->position));
                $this->reconcileExhaustion($election->refresh());
                $processed++;
            }, 3);
        }

        return $processed;
    }

    /**
     * Finalize an offer/appointment chain only when no response is still
     * pending, no accepted offer is waiting to be appointed, and at least one
     * required position can no longer reach its frozen seat count from this
     * cycle's active appointments, reserved offers and never-offered ranking.
     */
    public function reconcileExhaustion(Election $election): Election
    {
        $election = Election::query()->findOrFail($election->id);
        $status = $this->lifecycle->currentStatus($election);
        if (! in_array($status, [ElectionLifecycleStatus::AwaitingAcceptance, ElectionLifecycleStatus::Appointing], true)) {
            return $election;
        }

        if (ElectionResponsibilityOffer::query()
            ->where('election_id', $election->id)
            ->where('status', ElectionResponsibilityOfferStatus::Pending->value)
            ->exists()) {
            return $election;
        }

        $activeAppointmentKeys = ElectionAppointment::query()
            ->where('election_id', $election->id)
            ->where('group_id', $election->group_id)
            ->where('appointment_kind', 'direct')
            ->where('status', 'active')
            ->get(['user_id', 'position'])
            ->map(fn (ElectionAppointment $appointment) => $appointment->position.':'.(int) $appointment->user_id)
            ->flip();

        $acceptedWaiting = ElectionResponsibilityOffer::query()
            ->where('election_id', $election->id)
            ->where('status', ElectionResponsibilityOfferStatus::Accepted->value)
            ->get(['candidate_user_id', 'position'])
            ->contains(fn (ElectionResponsibilityOffer $offer) => ! $activeAppointmentKeys->has(
                $offer->position.':'.(int) $offer->candidate_user_id
            ));
        if ($acceptedWaiting) {
            return $election;
        }

        try {
            $policy = $this->policyResolver->resolveForElection($election);
        } catch (RuntimeException) {
            $policy = $this->policyResolver->resolveForGroup($election->group);
        }

        foreach (ElectionPosition::cases() as $position) {
            $seatCount = $position === ElectionPosition::Manager
                ? $this->policyResolver->managerSeatCount($policy)
                : $this->policyResolver->inspectorSeatCount($policy);
            if ($seatCount <= 0) {
                continue;
            }

            $appointedIds = ElectionAppointment::query()
                ->where('election_id', $election->id)
                ->where('group_id', $election->group_id)
                ->where('position', $position->value)
                ->where('appointment_kind', 'direct')
                ->where('status', 'active')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $reservedIds = ElectionResponsibilityOffer::query()
                ->where('election_id', $election->id)
                ->where('position', $position->value)
                ->whereIn('status', [
                    ElectionResponsibilityOfferStatus::Pending->value,
                    ElectionResponsibilityOfferStatus::Accepted->value,
                ])
                ->pluck('candidate_user_id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => in_array($id, $appointedIds, true))
                ->all();

            $offeredIds = ElectionResponsibilityOffer::query()
                ->where('election_id', $election->id)
                ->where('position', $position->value)
                ->pluck('candidate_user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $remainingRankedIds = ElectionTallyResult::query()
                ->where('election_id', $election->id)
                ->where('position', $position->value)
                ->when($offeredIds !== [], fn ($query) => $query->whereNotIn('candidate_user_id', $offeredIds))
                ->pluck('candidate_user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $potential = array_unique(array_merge($appointedIds, $reservedIds, $remainingRankedIds));
            if (count($potential) < $seatCount) {
                return $this->lifecycle->transition(
                    $election,
                    ElectionLifecycleStatus::Exhausted,
                    'ranked_responsibility_candidates_exhausted_before_all_seats_filled',
                    'election_responsibility_offer_service',
                );
            }
        }

        return $election;
    }

    public function summary(Election $election): array
    {
        return [
            'election_id' => (int) $election->id,
            'pending' => ElectionResponsibilityOffer::where('election_id', $election->id)
                ->where('status', ElectionResponsibilityOfferStatus::Pending->value)->count(),
            'accepted' => ElectionResponsibilityOffer::where('election_id', $election->id)
                ->where('status', ElectionResponsibilityOfferStatus::Accepted->value)->count(),
        ];
    }

    private function fillOpenSlots(Election $election, ElectionPosition $position): void
    {
        try {
            $policy = $this->policyResolver->resolveForElection($election);
        } catch (RuntimeException) {
            $policy = $this->policyResolver->resolveForGroup($election->group);
        }

        $seatCount = $position === ElectionPosition::Manager
            ? $this->policyResolver->managerSeatCount($policy)
            : $this->policyResolver->inspectorSeatCount($policy);

        if ($seatCount <= 0) {
            return;
        }

        $responseDays = $this->policyResolver->responseDurationDays($policy);

        $occupying = ElectionResponsibilityOffer::query()
            ->where('election_id', $election->id)
            ->where('position', $position->value)
            ->whereIn('status', [
                ElectionResponsibilityOfferStatus::Pending->value,
                ElectionResponsibilityOfferStatus::Accepted->value,
            ])->count();

        if ($occupying >= $seatCount) {
            return;
        }

        $alreadyOffered = ElectionResponsibilityOffer::query()
            ->where('election_id', $election->id)
            ->where('position', $position->value)
            ->pluck('candidate_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $contract = $this->contractForElection($position, $election);
        $ranked = ElectionTallyResult::query()
            ->where('election_id', $election->id)
            ->where('position', $position->value)
            ->orderBy('rank')
            ->get();

        foreach ($ranked as $row) {
            if ($occupying >= $seatCount) break;
            if (in_array((int) $row->candidate_user_id, $alreadyOffered, true)) continue;

            if (! $this->isCurrentlyEligible($election, (int) $row->candidate_user_id)) {
                $offer = ElectionResponsibilityOffer::create([
                    'election_id' => $election->id,
                    'candidate_user_id' => (int) $row->candidate_user_id,
                    'position' => $position->value,
                    'ranking_position' => (int) $row->rank,
                    'contract_version_id' => $contract->id,
                    'status' => ElectionResponsibilityOfferStatus::Ineligible,
                    'offered_at' => now(),
                    'expires_at' => now(),
                    'responded_at' => now(),
                    'eligibility_checked_at' => now(),
                    'resolution_reason' => 'candidate_ineligible_before_offer',
                ]);
                $this->syncCandidateProjection($offer);
                $alreadyOffered[] = (int) $row->candidate_user_id;
                continue;
            }

            $offer = ElectionResponsibilityOffer::create([
                'election_id' => $election->id,
                'candidate_user_id' => (int) $row->candidate_user_id,
                'position' => $position->value,
                'ranking_position' => (int) $row->rank,
                'contract_version_id' => $contract->id,
                'status' => ElectionResponsibilityOfferStatus::Pending,
                'offered_at' => now(),
                'expires_at' => now()->addDays($responseDays),
                'eligibility_checked_at' => now(),
                'response_metadata' => [
                    'policy_version_id' => $election->policy_version_id,
                    'response_duration_days' => $responseDays,
                    'contract_version_id' => (int) $contract->id,
                    'contract_frozen_by_policy' => $policy instanceof ElectionPolicyVersion,
                ],
            ]);
            $this->syncCandidateProjection($offer);
            $alreadyOffered[] = (int) $row->candidate_user_id;
            $occupying++;
        }
    }

    private function contractForElection(ElectionPosition $position, Election $election): ElectionResponsibilityContractVersion
    {
        if ($election->policy_version_id !== null) {
            $policy = $this->policyResolver->resolveForElection($election);
            $frozenId = $this->frozenContractId($policy, $position);
            if ($frozenId === null) {
                throw new RuntimeException("Election [{$election->id}] policy version [{$policy->id}] has no frozen responsibility contract for [{$position->value}].");
            }

            $contract = ElectionResponsibilityContractVersion::query()->find($frozenId);
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

    private function frozenContractId(ElectionPolicyVersion $policy, ElectionPosition $position): ?int
    {
        $id = $position === ElectionPosition::Manager
            ? $policy->manager_contract_version_id
            : $policy->inspector_contract_version_id;

        return $id === null ? null : (int) $id;
    }

    private function assertPendingForCandidate(ElectionResponsibilityOffer $offer, int $candidateUserId): void
    {
        if ((int) $offer->candidate_user_id !== $candidateUserId) {
            throw ValidationException::withMessages(['offer' => 'این دعوت متعلق به حساب شما نیست.']);
        }
        if ($offer->status !== ElectionResponsibilityOfferStatus::Pending) {
            throw ValidationException::withMessages(['offer' => 'این دعوت دیگر در وضعیت پاسخ‌گویی نیست.']);
        }
    }

    private function isCurrentlyEligible(Election $election, int $candidateUserId): bool
    {
        $user = User::query()->find($candidateUserId);
        if ($user === null || (bool) $user->is_system) return false;

        $membership = GroupUser::query()
            ->where('group_id', $election->group_id)
            ->where('user_id', $candidateUserId)
            ->first();

        if ($membership === null || (int) $membership->status !== 1) return false;

        $role = (int) $membership->role;
        return $role >= 1 && $role !== 4;
    }

    private function resolve(ElectionResponsibilityOffer $offer, ElectionResponsibilityOfferStatus $status, string $reason): void
    {
        $offer->forceFill([
            'status' => $status,
            'responded_at' => now(),
            'resolution_reason' => $reason,
        ])->save();
        $this->syncCandidateProjection($offer);
    }

    private function syncCandidateProjection(ElectionResponsibilityOffer $offer): void
    {
        $acceptance = match ($offer->status) {
            ElectionResponsibilityOfferStatus::Pending => ElectionAcceptanceStatus::Pending,
            ElectionResponsibilityOfferStatus::Accepted => ElectionAcceptanceStatus::Accepted,
            ElectionResponsibilityOfferStatus::Declined => ElectionAcceptanceStatus::Declined,
            ElectionResponsibilityOfferStatus::Expired => ElectionAcceptanceStatus::Expired,
            ElectionResponsibilityOfferStatus::Ineligible => null,
        };

        $legacyStatus = match ($offer->status) {
            ElectionResponsibilityOfferStatus::Pending => '1',
            ElectionResponsibilityOfferStatus::Accepted => '2',
            ElectionResponsibilityOfferStatus::Declined,
            ElectionResponsibilityOfferStatus::Expired,
            ElectionResponsibilityOfferStatus::Ineligible => '0',
        };

        $candidate = Candidate::query()->firstOrNew([
            'election_id' => $offer->election_id,
            'user_id' => $offer->candidate_user_id,
            'position' => $offer->position,
        ]);

        $candidate->accept_status = $legacyStatus;
        $candidate->acceptance_status = $acceptance?->value;
        $candidate->save();
    }
}
