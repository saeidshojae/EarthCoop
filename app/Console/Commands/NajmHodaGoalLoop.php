<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaAutonomousGoalLoopService;
use Illuminate\Console\Command;

class NajmHodaGoalLoop extends Command
{
    protected $signature = 'najm-hoda:goal-loop
        {--goal=* : Goal(s) for this run}
        {--context-limit= : Number of recent runtime events for context}
        {--apply : Allow low-risk apply mode when enabled by config}';

    protected $description = 'Run Najm Hoda autonomous goal loop skeleton';

    public function __construct(
        protected NajmHodaAutonomousGoalLoopService $goalLoopService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $goals = array_values(array_filter(
            array_map(static fn ($goal): string => trim((string) $goal), (array) $this->option('goal')),
            static fn (string $goal): bool => $goal !== ''
        ));
        $apply = (bool) $this->option('apply');

        $contextLimit = $this->option('context-limit');
        $contextLimit = is_numeric($contextLimit) ? (int) $contextLimit : null;

        $result = $this->goalLoopService->run($goals, $apply, $contextLimit);

        if (!(bool) ($result['executed'] ?? false)) {
            $this->warn('Najm Hoda autonomous goal loop skipped: ' . (string) ($result['reason'] ?? 'unknown'));
            return self::SUCCESS;
        }

        $this->line('Najm Hoda Goal Loop');
        $this->table(
            ['Run ID', 'Goals', 'Plan items', 'Apply requested'],
            [[
                (string) ($result['run_id'] ?? 'N/A'),
                (string) count((array) ($result['goals'] ?? [])),
                (string) count((array) ($result['plan'] ?? [])),
                ((bool) ($result['apply_requested'] ?? false)) ? 'yes' : 'no',
            ]]
        );

        $firstPlan = data_get($result, 'plan.0', []);
        if (is_array($firstPlan) && !empty($firstPlan)) {
            $this->line(sprintf(
                'Top action: %s (%s, %s)',
                (string) ($firstPlan['action'] ?? 'unknown'),
                (string) ($firstPlan['mode'] ?? 'propose'),
                (string) ($firstPlan['reason'] ?? 'n/a')
            ));
        }

        return self::SUCCESS;
    }
}
