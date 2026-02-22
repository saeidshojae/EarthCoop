<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaMultiHorizonGoalReviewService;
use Illuminate\Console\Command;

class NajmHodaMultiHorizonGoalsReview extends Command
{
    protected $signature = 'najm-hoda:multi-goals-review
        {--scope=global : Query scope}
        {--actor= : Actor user id}
        {--window=24 : KPI window hours}
        {--limit=2000 : Runtime event limit}
        {--fail-on-regression : Return non-zero exit code on regressing status}';

    protected $description = 'Review multi-horizon goals trend and detect backlog regression';

    public function __construct(
        protected NajmHodaMultiHorizonGoalReviewService $reviewService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Multi-horizon goal review skipped.');
            return self::SUCCESS;
        }

        $scope = trim((string) ($this->option('scope') ?? 'global'));
        $actorId = is_numeric($this->option('actor')) ? (int) $this->option('actor') : null;
        $windowHours = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
        $eventLimit = is_numeric($this->option('limit')) ? (int) $this->option('limit') : 2000;

        $result = $this->reviewService->review([
            'scope' => $scope,
            'actor_id' => $actorId,
            'window_hours' => $windowHours,
            'event_limit' => $eventLimit,
        ]);

        $this->line('Najm Hoda Multi-Horizon Goal Review');
        $this->table(['Key', 'Value'], [
            ['scope', (string) ($result['scope'] ?? '')],
            ['status', (string) ($result['status'] ?? 'unknown')],
            ['backlog_delta', (string) data_get($result, 'comparison.backlog_delta', 0)],
            ['high_priority_delta', (string) data_get($result, 'comparison.high_priority_delta', 0)],
            ['daily_goal_delta', (string) data_get($result, 'comparison.daily_goal_delta', 0)],
        ]);

        if ((bool) $this->option('fail-on-regression') && (string) ($result['status'] ?? '') === 'regressing') {
            $this->warn('Multi-horizon goal trend is regressing.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

