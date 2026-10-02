<?php

namespace App\Services\Communication;

use App\Jobs\Communication\ResolveCommunicationRunAudience;
use App\Models\CommunicationRuleSchedule;
use App\Models\CommunicationRun;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

class CommunicationScheduleService
{
    public function processDue(CarbonInterface $now): int
    {
        $processed = 0;

        $schedules = CommunicationRuleSchedule::query()
            ->with('rule')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->orderBy('id')
            ->get();

        foreach ($schedules as $schedule) {
            $rule = $schedule->rule;
            if (! $rule || ! $rule->is_active || $rule->trigger_type !== 'scheduled') {
                continue;
            }

            $runKey = 'schedule:'.$rule->id.':'.$now->copy()->utc()->format('YmdHis');

            try {
                $run = CommunicationRun::query()->firstOrCreate(
                    ['run_key' => $runKey],
                    [
                        'communication_rule_id' => $rule->id,
                        'status' => 'pending',
                        'started_at' => $now,
                    ],
                );
            } catch (QueryException) {
                $run = CommunicationRun::query()->where('run_key', $runKey)->first();
            }

            if (! $run || ! $run->wasRecentlyCreated) {
                continue;
            }

            $schedule->forceFill([
                'last_run_at' => $now,
                'next_run_at' => $this->nextRunAt($schedule, $now),
            ])->save();

            ResolveCommunicationRunAudience::dispatch($run->id)->onQueue('communications-bulk');
            $processed++;
        }

        return $processed;
    }

    private function nextRunAt(CommunicationRuleSchedule $schedule, CarbonInterface $now): CarbonInterface
    {
        $definition = (array) $schedule->schedule_definition;
        $interval = max(1, (int) ($definition['interval'] ?? 1));

        return match ($schedule->frequency) {
            'hourly' => $now->copy()->addHours($interval),
            'daily' => $now->copy()->addDays($interval),
            'weekly' => $now->copy()->addWeeks($interval),
            default => $now->copy()->addDays($interval),
        };
    }
}
