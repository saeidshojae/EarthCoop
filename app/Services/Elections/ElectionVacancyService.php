<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionPosition;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Models\ElectionAppointment;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\ElectionVacancy;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ElectionVacancyService
{
    public function __construct(private readonly ElectionAppointmentService $appointments) {}

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
            $contract = $this->activeContract($position);
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
                    'expires_at' => $eligible ? now()->addDays(ElectionResponsibilityOfferService::RESPONSE_WINDOW_DAYS) : now(),
                    'responded_at' => $eligible ? null : now(),
                    'eligibility_checked_at' => now(),
                    'resolution_reason' => $eligible ? 'post_appointment_vacancy_ranked_backfill' : 'candidate_ineligible_for_post_appointment_vacancy',
                    'response_metadata' => [
                        'vacancy_id' => (int) $vacancy->id,
                        'source_appointment_id' => (int) $vacancy->source_appointment_id,
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
