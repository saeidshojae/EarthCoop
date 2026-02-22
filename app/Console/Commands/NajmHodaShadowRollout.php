<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaShadowLiveRolloutService;
use Illuminate\Console\Command;

class NajmHodaShadowRollout extends Command
{
    protected $signature = 'najm-hoda:shadow-rollout
        {--status : Show current shadow-to-live rollout state}
        {--evaluate : Evaluate current stage guardrails}
        {--advance : Advance to next stage if guardrails pass}
        {--fallback : Fallback to a target stage}
        {--stage= : Fallback target stage (shadow|limited_live|supervised_live|autonomous_live)}
        {--history=0 : Show latest N history rows}
        {--window=24 : Evaluation window in hours}
        {--reason= : Optional reason}';

    protected $description = 'Manage shadow-to-live rollout guardrails for Najm Hoda autonomy';

    public function __construct(
        protected NajmHodaShadowLiveRolloutService $rolloutService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Shadow rollout skipped.');
            return self::SUCCESS;
        }

        $history = (int) $this->option('history');
        if ($history > 0) {
            $rows = $this->rolloutService->history($history);
            if (empty($rows)) {
                $this->line('No rollout history found.');
                return self::SUCCESS;
            }
            $this->table(
                ['Timestamp', 'Type', 'From', 'To', 'Decision/Reason'],
                array_map(static function (array $row): array {
                    return [
                        (string) ($row['timestamp'] ?? '-'),
                        (string) ($row['type'] ?? '-'),
                        (string) ($row['from_stage'] ?? '-'),
                        (string) ($row['to_stage'] ?? '-'),
                        (string) ($row['decision'] ?? (string) ($row['reason'] ?? '-')),
                    ];
                }, $rows)
            );
            return self::SUCCESS;
        }

        $window = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
        $reason = $this->option('reason') !== null ? (string) $this->option('reason') : null;

        if ((bool) $this->option('evaluate')) {
            $result = $this->rolloutService->evaluate($window, false);
            if (!(bool) ($result['success'] ?? false)) {
                $this->error('Rollout evaluation failed: ' . (string) ($result['reason'] ?? 'unknown'));
                return self::FAILURE;
            }
            $this->line('Decision: ' . (string) data_get($result, 'report.decision', 'hold'));
            return $this->printStatus();
        }

        if ((bool) $this->option('advance')) {
            $result = $this->rolloutService->advance(auth()->id(), $reason);
            if (!(bool) ($result['success'] ?? false)) {
                $this->error('Advance failed: ' . (string) ($result['reason'] ?? 'guardrails_not_passed'));
                return self::FAILURE;
            }
            $this->info('Rollout advanced to next stage.');
            return $this->printStatus();
        }

        if ((bool) $this->option('fallback')) {
            $result = $this->rolloutService->fallback(auth()->id(), $reason, (string) ($this->option('stage') ?? ''));
            if (!(bool) ($result['success'] ?? false)) {
                $this->error('Fallback failed.');
                return self::FAILURE;
            }
            $this->warn('Rollout fallback applied.');
            return $this->printStatus();
        }

        return $this->printStatus();
    }

    protected function printStatus(): int
    {
        $state = $this->rolloutService->status();
        $this->table(['Key', 'Value'], [
            ['stage', (string) ($state['stage'] ?? 'shadow')],
            ['status', (string) ($state['status'] ?? 'active')],
            ['last_evaluated_at', (string) ($state['last_evaluated_at'] ?? '')],
            ['last_decision', (string) ($state['last_decision'] ?? '')],
            ['last_decision_reason', (string) ($state['last_decision_reason'] ?? '')],
            ['last_transition_at', (string) ($state['last_transition_at'] ?? '')],
        ]);

        return self::SUCCESS;
    }
}
