<?php

namespace App\Console\Commands;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Services\Elections\ElectionLifecycleService;
use Illuminate\Console\Command;
use Throwable;

class ProcessElectionLifecycle extends Command
{
    protected $signature = 'elections:process-lifecycle
        {--limit=500 : Maximum elections to inspect in one tick}
        {--fail-on-error : Exit non-zero if any election fails processing}';

    protected $description = 'Advance due election lifecycle states through the canonical transactional state machine';

    public function handle(ElectionLifecycleService $lifecycle): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $processed = 0;
        $advanced = 0;
        $errors = 0;

        Election::query()
            ->where(function ($query) {
                $query->whereIn('lifecycle_status', [
                    ElectionLifecycleStatus::Scheduled->value,
                    ElectionLifecycleStatus::Open->value,
                ])->orWhere(function ($legacy) {
                    $legacy->whereNull('lifecycle_status')
                        ->where('is_closed', false);
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($elections) use (
                $lifecycle,
                $limit,
                &$processed,
                &$advanced,
                &$errors,
            ) {
                foreach ($elections as $election) {
                    if ($processed >= $limit) {
                        return false;
                    }

                    $processed++;
                    $before = $lifecycle->currentStatus($election);

                    try {
                        $afterElection = $lifecycle->advanceDue($election);
                        $after = $lifecycle->currentStatus($afterElection);
                        if ($after !== $before) {
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

        $this->line("processed={$processed} advanced={$advanced} errors={$errors}");

        if ($errors > 0 && $this->option('fail-on-error')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
