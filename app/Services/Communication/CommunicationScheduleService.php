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
        $now = $now->copy()->utc();
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

            $scheduledFor = $schedule->next_run_at?->copy()->utc();
            if ($scheduledFor === null) {
                continue;
            }

            $runKey = 'schedule:'.$rule->id.':'.$scheduledFor->format('YmdHis');

            try {
                $run = CommunicationRun::query()->firstOrCreate(
                    ['run_key' => $runKey],
                    [
                        'communication_rule_id' => $rule->id,
                        'status' => 'pending',
                        'scheduled_for' => $scheduledFor,
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
                'next_run_at' => $this->nextRunAt($schedule, $scheduledFor),
            ])->save();

            ResolveCommunicationRunAudience::dispatch($run->id)->onQueue('communications-bulk');
            $processed++;
        }

        return $processed;
    }

    private function nextRunAt(CommunicationRuleSchedule $schedule, CarbonInterface $scheduledFor): CarbonInterface
    {
        $definition = (array) $schedule->schedule_definition;
        $interval = max(1, (int) ($definition['interval'] ?? 1));
        $timezone = trim((string) $schedule->timezone) !== ''
            ? (string) $schedule->timezone
            : 'UTC';
        $localOccurrence = $scheduledFor->copy()->setTimezone($timezone);

        $nextLocalOccurrence = match ($schedule->frequency) {
            'hourly' => $localOccurrence->addHours($interval),
            'daily' => $localOccurrence->addDays($interval),
            'weekly' => $localOccurrence->addWeeks($interval),
            default => $localOccurrence->addDays($interval),
        };

        return $nextLocalOccurrence->utc();
    }
}
