<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaCrossModuleCapabilityOrchestratorService;
use Illuminate\Console\Command;

class NajmHodaOrchestrate extends Command
{
    protected $signature = 'najm-hoda:orchestrate
        {--from-multi-goals : Build chain from multi-goals backlog/review}
        {--goal=* : Goal scope list for safety gate}
        {--actor= : Actor user id for delegation checks}
        {--scope=global : Query scope for multi-goals source}
        {--window=24 : KPI window hours for multi-goals source}
        {--limit=2000 : Runtime event limit for multi-goals source}
        {--apply : Allow apply mode for low-risk actions}';

    protected $description = 'Run cross-module capability orchestration chain with stepwise rollback on failure';

    public function __construct(
        protected NajmHodaCrossModuleCapabilityOrchestratorService $orchestratorService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Orchestration skipped.');
            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $goals = array_values(array_filter(
            array_map(static fn ($g): string => trim((string) $g), (array) $this->option('goal')),
            static fn (string $g): bool => $g !== ''
        ));
        $actor = is_numeric($this->option('actor')) ? (int) $this->option('actor') : null;

        $result = [];
        if ((bool) $this->option('from-multi-goals')) {
            $scope = trim((string) ($this->option('scope') ?? 'global'));
            $window = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
            $limit = is_numeric($this->option('limit')) ? (int) $this->option('limit') : 2000;

            $result = $this->orchestratorService->orchestrateFromMultiGoals([
                'scope' => $scope,
                'window_hours' => $window,
                'event_limit' => $limit,
            ], $goals, $apply, $actor);
        } else {
            $this->warn('No source selected. Use --from-multi-goals.');
            return self::SUCCESS;
        }

        $this->line('Najm Hoda Cross-Module Orchestrator');
        $this->table(['Key', 'Value'], [
            ['status', (string) ($result['status'] ?? 'unknown')],
            ['reason', (string) ($result['reason'] ?? '')],
            ['run_id', (string) ($result['run_id'] ?? '')],
            ['steps', (string) count((array) ($result['steps'] ?? []))],
            ['rollback', (string) count((array) ($result['rollback'] ?? []))],
        ]);

        return self::SUCCESS;
    }
}
