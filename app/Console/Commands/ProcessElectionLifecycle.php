<?php

namespace App\Console\Commands;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\Group;
use App\Services\Elections\ElectionCycleService;
use App\Services\Elections\ElectionLifecycleService;
use App\Services\Elections\ElectionResponsibilityOfferService;
use Illuminate\Console\Command;
use Throwable;

class ProcessElectionLifecycle extends Command
{
    protected $signature = 'elections:process-lifecycle
        {--limit=500 : Maximum groups/elections/offers to inspect in one tick}
        {--fail-on-error : Exit non-zero if any election action fails processing}';

    protected $description = 'Create election cycles, advance due states and expire responsibility offers through canonical server-side services';

    public function handle(
        ElectionCycleService $cycles,
        ElectionLifecycleService $lifecycle,
        ElectionResponsibilityOfferService $offers,
    ): int {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $groupsProcessed = 0;
        $cyclesCreated = 0;
        $processed = 0;
        $advanced = 0;
        $expiredOffers = 0;
        $errors = 0;

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

        try {
            $expiredOffers = $offers->expireDue($limit);
        } catch (Throwable $exception) {
            $errors++;
            report($exception);
            $this->error("Responsibility offers: {$exception->getMessage()}");
        }

        // Keep the legacy processed/advanced/errors sequence stable for operators,
        // log parsers and regression checks; append the E7 metric afterwards.
        $this->line(
            "groups={$groupsProcessed} cycles_created={$cyclesCreated} processed={$processed} advanced={$advanced} errors={$errors} expired_offers={$expiredOffers}"
        );

        return ($errors > 0 && $this->option('fail-on-error')) ? self::FAILURE : self::SUCCESS;
    }
}
