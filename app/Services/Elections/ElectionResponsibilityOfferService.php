<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Models\Election;
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
                $this->activeContract($position);
                $this->fillOpenSlots($locked, $position);
            }

            $locked = $this->lifecycle->transition(
                $locked,
                ElectionLifecycleStatus::AwaitingAcceptance,
                'responsibility_offers_started',
                'election_responsibility_offer_service',
            );

            return $this->summary($locked);
        }, 3);
    }

    public function accept(ElectionResponsibilityOffer $offer, int $candidateUserId): ElectionResponsibilityOffer
    {
        return DB::transaction(function () use ($offer, $candidateUserId): ElectionResponsibilityOffer {
            $locked = ElectionResponsibilityOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $election = Election::query()->lockForUpdate()->findOrFail($locked->election_id);
            $this->assertPendingForCandidate($locked, $candidateUserId);

            if ($locked->expires_at->isPast()) {
                $this->resolve($locked, ElectionResponsibilityOfferStatus::Expired, 'response_deadline_elapsed');
                $this->fillOpenSlots($election, ElectionPosition::from($locked->position));
                throw ValidationException::withMessages(['offer' => 'مهلت پذیرش این دعوت پایان یافته است.']);
            }

            if (! $this->isCurrentlyEligible($election, $candidateUserId)) {
                $this->resolve($locked, ElectionResponsibilityOfferStatus::Ineligible, 'candidate_no_longer_eligible');
                $this->fillOpenSlots($election, ElectionPosition::from($locked->position));
                throw ValidationException::withMessages(['offer' => 'شرایط عضویت فعال برای پذیرش این مسئولیت برقرار نیست.']);
            }

            $locked->forceFill([
                'status' => ElectionResponsibilityOfferStatus::Accepted,
                'responded_at' => now(),
                'eligibility_checked_at' => now(),
                'resolution_reason' => 'candidate_accepted_contract',
                'response_metadata' => [
                    'candidate_user_id' => $candidateUserId,
                    'contract_version_id' => (int) $locked->contract_version_id,
                ],
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    public function decline(ElectionResponsibilityOffer $offer, int $candidateUserId): ElectionResponsibilityOffer
    {
        return DB::transaction(function () use ($offer, $candidateUserId): ElectionResponsibilityOffer {
            $locked = ElectionResponsibilityOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $election = Election::query()->lockForUpdate()->findOrFail($locked->election_id);
            $this->assertPendingForCandidate($locked, $candidateUserId);

            $this->resolve($locked, ElectionResponsibilityOfferStatus::Declined, 'candidate_declined_contract');
            $this->fillOpenSlots($election, ElectionPosition::from($locked->position));

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
                $processed++;
            }, 3);
        }

        return $processed;
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
        $policy = $this->policyResolver->resolveForGroup($election->group);
        $seatCount = $position === ElectionPosition::Manager
            ? $this->policyResolver->managerSeatCount($policy)
            : $this->policyResolver->inspectorSeatCount($policy);

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

        $contract = $this->activeContract($position);
        $ranked = ElectionTallyResult::query()
            ->where('election_id', $election->id)
            ->where('position', $position->value)
            ->orderBy('rank')
            ->get();

        foreach ($ranked as $row) {
            if ($occupying >= $seatCount) {
                break;
            }
            if (in_array((int) $row->candidate_user_id, $alreadyOffered, true)) {
                continue;
            }
            if (! $this->isCurrentlyEligible($election, (int) $row->candidate_user_id)) {
                ElectionResponsibilityOffer::create([
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
                $alreadyOffered[] = (int) $row->candidate_user_id;
                continue;
            }

            ElectionResponsibilityOffer::create([
                'election_id' => $election->id,
                'candidate_user_id' => (int) $row->candidate_user_id,
                'position' => $position->value,
                'ranking_position' => (int) $row->rank,
                'contract_version_id' => $contract->id,
                'status' => ElectionResponsibilityOfferStatus::Pending,
                'offered_at' => now(),
                'expires_at' => now()->addDays(self::RESPONSE_WINDOW_DAYS),
                'eligibility_checked_at' => now(),
            ]);
            $alreadyOffered[] = (int) $row->candidate_user_id;
            $occupying++;
        }
    }

    private function activeContract(ElectionPosition $position): ElectionResponsibilityContractVersion
    {
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
        if ($user === null || (bool) $user->is_system) {
            return false;
        }

        $membership = GroupUser::query()
            ->where('group_id', $election->group_id)
            ->where('user_id', $candidateUserId)
            ->first();

        if ($membership === null || (int) $membership->status !== 1) {
            return false;
        }

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
    }
}
