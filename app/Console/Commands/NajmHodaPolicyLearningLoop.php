<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaAdaptivePolicyLearningService;
use Illuminate\Console\Command;

class NajmHodaPolicyLearningLoop extends Command
{
    protected $signature = 'najm-hoda:policy-learning-loop
        {--window=24 : Analysis window hours}
        {--apply : Apply adaptive override}
        {--clear : Clear current adaptive override}';

    protected $description = 'Run adaptive safety/policy learning loop based on drift and governance metrics';

    public function __construct(
        protected NajmHodaAdaptivePolicyLearningService $policyLearningService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Policy-learning loop skipped.');
            return self::SUCCESS;
        }

        if ((bool) $this->option('clear')) {
            $this->policyLearningService->clearOverride(auth()->id(), 'manual_clear_from_command');
            $this->info('Adaptive override cleared.');
            return self::SUCCESS;
        }

        $window = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
        $apply = (bool) $this->option('apply');
        $report = $this->policyLearningService->analyze($window, $apply);

        $this->table(['Key', 'Value'], [
            ['window_hours', (string) ($report['window_hours'] ?? '')],
            ['drift_status', (string) data_get($report, 'input.policy_drift_status', '')],
            ['drift_rate', (string) data_get($report, 'input.policy_drift_rate', '')],
            ['success_rate', (string) data_get($report, 'input.auto_action_success_rate', '')],
            ['should_apply', (bool) ($report['should_apply'] ?? false) ? 'yes' : 'no'],
            ['applied', (bool) ($report['applied'] ?? false) ? 'yes' : 'no'],
            ['max_actions_per_run', (string) data_get($report, 'recommended_override.max_actions_per_run', '')],
            ['allow_apply_low_risk', data_get($report, 'recommended_override.allow_apply_low_risk', null) === true ? 'true' : 'false'],
        ]);

        return self::SUCCESS;
    }
}

