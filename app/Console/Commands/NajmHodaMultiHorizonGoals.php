<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaMultiHorizonGoalEngineService;
use Illuminate\Console\Command;

class NajmHodaMultiHorizonGoals extends Command
{
    protected $signature = 'najm-hoda:multi-goals
        {--scope=global : Query scope}
        {--actor= : Actor user id}
        {--window=24 : KPI window hours}
        {--limit=2000 : Runtime event limit}';

    protected $description = 'Generate multi-horizon autonomy goals and prioritized backlog';

    public function __construct(
        protected NajmHodaMultiHorizonGoalEngineService $engineService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Multi-horizon goal generation skipped.');
            return self::SUCCESS;
        }

        $scope = trim((string) ($this->option('scope') ?? 'global'));
        $actorId = is_numeric($this->option('actor')) ? (int) $this->option('actor') : null;
        $windowHours = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
        $eventLimit = is_numeric($this->option('limit')) ? (int) $this->option('limit') : 2000;

        $result = $this->engineService->buildBacklog([
            'scope' => $scope,
            'actor_id' => $actorId,
            'window_hours' => $windowHours,
            'event_limit' => $eventLimit,
        ]);

        $this->line('Najm Hoda Multi-Horizon Goal Engine');
        $this->table(['Key', 'Value'], [
            ['scope', (string) ($result['scope'] ?? '')],
            ['window_hours', (string) ($result['window_hours'] ?? '')],
            ['backlog_count', (string) count((array) ($result['backlog'] ?? []))],
            ['daily_goals', (string) count((array) data_get($result, 'horizons.daily', []))],
            ['weekly_goals', (string) count((array) data_get($result, 'horizons.weekly', []))],
            ['monthly_goals', (string) count((array) data_get($result, 'horizons.monthly', []))],
        ]);

        $top = array_slice((array) ($result['backlog'] ?? []), 0, 5);
        if (!empty($top)) {
            $this->table(['Priority', 'Horizon', 'Task ID', 'Trigger'], array_map(
                static fn (array $item): array => [
                    (string) ($item['priority'] ?? ''),
                    (string) ($item['horizon'] ?? ''),
                    (string) ($item['id'] ?? ''),
                    (string) ($item['trigger'] ?? ''),
                ],
                $top
            ));
        }

        return self::SUCCESS;
    }
}

