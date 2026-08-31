<?php

namespace App\Console\Commands;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\Group;
use App\Services\Elections\ElectionAppointmentService;
use App\Services\Elections\ElectionCycleService;
use App\Services\Elections\ElectionLifecycleService;
use App\Services\Elections\ElectionMeaningfulTrendNotificationService;
use App\Services\Elections\ElectionPolicyVersionService;
use App\Services\Elections\ElectionResponsibilityOfferService;
use App\Services\Elections\ElectionTallyService;
use App\Services\Elections\ElectionVacancyService;
use Illuminate\Console\Command;
use Throwable;

class ProcessElectionLifecycle extends Command
{
    protected $signature = 'elections:process-lifecycle
        {--limit=500 : Maximum groups/elections/offers/vacancies to inspect in one tick}
        {--fail-on-error : Exit non-zero if any election action fails processing}';

    protected $description = 'Create election cycles, activate effective policies, advance due states, tally stopped cycles, start responsibility offers, expire offers, apply appointments, backfill vacancies and emit privacy-safe meaningful trend alerts';

    public function handle(
        ElectionCycleService $cycles,
        ElectionLifecycleService $lifecycle,
        ElectionPolicyVersionService $policyVersions,
        ElectionTallyService $tally,
        ElectionResponsibilityOfferService $offers,
        ElectionAppointmentService $appointments,
        ElectionVacancyService $vacancies,
        ElectionMeaningfulTrendNotificationService $trendNotifications,
    ): int {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $groupsProcessed = 0;
        $cyclesCreated = 0;
        $processed = 0;
        $advanced = 0;
        $settlementProcessed = 0;
        $tallied = 0;
        $offersStarted = 0;
        $policiesSynced = 0;
        $expiredOffers = 0;
        $appointmentElections = 0;
        $vacancyProcessed = 0;
        $vacancyFilled = 0;
        $vacancyExhausted = 0;
        $trendProcessed = 0;
        $trendNotified = 0;
        $errors = 0;

        try {
            $policiesSynced = $policyVersions->syncEffectiveMirrors($limit);
        } catch (Throwable $exception) {
            $errors++;
            report($exception);
            $this->error("Election policy mirrors: {$exception->getMessage()}");
        }

        // First stop any due ballot windows. The group pass immediately below
        // can then open the successor collection window before result processing,
        // so a tally/offer failure never blocks continuous ballot availability.
        Election::query()
            ->where(function ($query) {
                $query->whereIn('lifecycle_status', [
                    ElectionLifecycleStatus::Scheduled->value,
                    ElectionLifecycleStatus::Open->value,
                ])->orWhere(function ($legacy) {
                    $legacy->whereNull('lifecycle_status')->where('is_closed', false);
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($elections) use ($lifecycle, $limit, &$processed, &$advanced, &$errors) {
                foreach ($elections as $election) {
                    if ($processed >= $limit) {
                        return false;
                    }
                    $processed++;
                    $before = $lifecycle->currentStatus($election);
                    try {
                        $afterElection = $lifecycle->advanceDue($election);
                        if ($lifecycle->currentStatus($afterElection) !== $before) {
                            $advanced++;
                        }
                    } catch (Throwable $exception) {
                        $errors++;
                        report($exception);
                        $this->error("Election {$election->id}: {$exception->getMessage()}");
                    }
                }
                return $processed < $limit;
            });

        Group::query()->orderBy('id')->chunkById(100, function ($groups) use (
            $cycles, $limit, &$groupsProcessed, &$cyclesCreated, &$errors,
        ) {
            foreach ($groups as $group) {
                if ($groupsProcessed >= $limit) {
                    return false;
                }
                $groupsProcessed++;
                $before = Election::where('group_id', $group->id)->count();
                try {
                    $cycles->ensureForGroup($group);
                    $after = Election::where('group_id', $group->id)->count();
                    if ($after > $before) {
                        $cyclesCreated += ($after - $before);
                    }
                } catch (Throwable $exception) {
                    $errors++;
                    report($exception);
                    $this->error("Group {$group->id}: {$exception->getMessage()}");
                }
            }
            return $groupsProcessed < $limit;
        });

        // A stopped election must not depend on a manual HTTP endpoint to make
        // progress. Every tick deterministically reconciles Closed/Tallying to
        // persisted tally results and then starts the responsibility-offer chain.
        Election::query()
            ->whereIn('lifecycle_status', [
                ElectionLifecycleStatus::Closed->value,
                ElectionLifecycleStatus::Tallying->value,
            ])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Election $election) use (
                $lifecycle, $tally, $offers,
                &$settlementProcessed, &$tallied, &$offersStarted, &$errors,
            ): void {
                $settlementProcessed++;
                try {
                    $before = $lifecycle->currentStatus($election);
                    $tally->tally($election);
                    if ($before === ElectionLifecycleStatus::Closed) {
                        $tallied++;
                    }

                    $election = Election::query()->findOrFail($election->id);
                    if ($lifecycle->currentStatus($election) === ElectionLifecycleStatus::Tallying) {
                        $offers->start($election);
                        $offersStarted++;
                    }
                } catch (Throwable $exception) {
                    $errors++;
                    report($exception);
                    $this->error("Election {$election->id} tally/offers: {$exception->getMessage()}");
                }
            });

        try {
            $expiredOffers = $offers->expireDue($limit);
        } catch (Throwable $exception) {
            $errors++;
            report($exception);
            $this->error("Responsibility offers: {$exception->getMessage()}");
        }

        Election::query()
            ->whereIn('lifecycle_status', [
                ElectionLifecycleStatus::AwaitingAcceptance->value,
                ElectionLifecycleStatus::Appointing->value,
            ])
            ->whereHas('responsibilityOffers', fn ($query) => $query->where('status', 'accepted'))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Election $election) use ($appointments, $offers, &$appointmentElections, &$errors): void {
                try {
                    $appointments->process($election);
                    $offers->reconcileExhaustion($election->refresh());
                    $appointmentElections++;
                } catch (Throwable $exception) {
                    $errors++;
                    report($exception);
                    $this->error("Election {$election->id} appointments: {$exception->getMessage()}");
                }
            });

        // Also reconcile offer chains with no accepted appointments to process
        // (for example a cycle whose entire ranking expired or became ineligible).
        Election::query()
            ->whereIn('lifecycle_status', [
                ElectionLifecycleStatus::AwaitingAcceptance->value,
                ElectionLifecycleStatus::Appointing->value,
            ])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Election $election) use ($offers, &$errors): void {
                try {
                    $offers->reconcileExhaustion($election);
                } catch (Throwable $exception) {
                    $errors++;
                    report($exception);
                    $this->error("Election {$election->id} exhaustion: {$exception->getMessage()}");
                }
            });

        try {
            $vacancySummary = $vacancies->processDue($limit);
            $vacancyProcessed = (int) ($vacancySummary['processed'] ?? 0);
            $vacancyFilled = (int) ($vacancySummary['filled'] ?? 0);
            $vacancyExhausted = (int) ($vacancySummary['exhausted'] ?? 0);
        } catch (Throwable $exception) {
            $errors++;
            report($exception);
            $this->error("Election vacancies: {$exception->getMessage()}");
        }

        try {
            $trendSummary = $trendNotifications->processDue($limit);
            $trendProcessed = (int) ($trendSummary['processed'] ?? 0);
            $trendNotified = (int) ($trendSummary['notified'] ?? 0);
            $errors += (int) ($trendSummary['errors'] ?? 0);
        } catch (Throwable $exception) {
            $errors++;
            report($exception);
            $this->error("Election trend notifications: {$exception->getMessage()}");
        }

        // Keep the legacy processed/advanced/errors sequence stable for operators,
        // log parsers and regression checks; append newer metrics afterwards.
        $this->line(
            "groups={$groupsProcessed} cycles_created={$cyclesCreated} processed={$processed} advanced={$advanced} errors={$errors} settlement_processed={$settlementProcessed} tallied={$tallied} offers_started={$offersStarted} policies_synced={$policiesSynced} expired_offers={$expiredOffers} appointment_elections={$appointmentElections} vacancy_processed={$vacancyProcessed} vacancy_filled={$vacancyFilled} vacancy_exhausted={$vacancyExhausted} trend_processed={$trendProcessed} trend_notified={$trendNotified}"
        );

        return ($errors > 0 && $this->option('fail-on-error')) ? self::FAILURE : self::SUCCESS;
    }
}
