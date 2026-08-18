<?php

namespace App\Console\Commands;

use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Services\PublicExecutionBridge;
use App\Modules\NajmBahar\Services\GovernanceExecutionOutboxConsumer;
use Illuminate\Console\Command;
use Throwable;

class NajmBaharConsumeGovernanceOutbox extends Command
{
    protected $signature = 'najm-bahar:consume-governance-outbox
        {--limit=25 : Maximum number of actions to consume in one run}
        {--retry-failed : Include previously failed actions}
        {--recover-dead-letter= : Explicit dead-letter action ID to reset after operator review}';

    protected $description = 'Consume authorized governance execution outbox actions inside the Najm Bahar monetary boundary.';

    public function handle(GovernanceExecutionOutboxConsumer $consumer): int
    {
        $recoverId = (int) ($this->option('recover-dead-letter') ?: 0);
        if ($recoverId > 0) {
            try {
                $action = EconomicAction::findOrFail($recoverId);
                $recovered = $consumer->recoverDeadLetter($action);
                $this->info('Recovered dead-letter action #' . $recovered->id . ' to failed/retryable state.');
                return self::SUCCESS;
            } catch (Throwable $e) {
                $this->error('Dead-letter recovery failed: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $statuses = $this->option('retry-failed') ? ['pending', 'failed'] : ['pending'];

        $actionIds = EconomicAction::query()
            ->where('action_type', PublicExecutionBridge::PUBLIC_PROJECT_EXECUTION_AUTHORIZED)
            ->whereIn('status', $statuses)
            ->where('attempts', '<', GovernanceExecutionOutboxConsumer::MAX_ATTEMPTS)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($actionIds->isEmpty()) {
            $this->info('No governance execution outbox actions are ready for Najm Bahar consumption.');
            return self::SUCCESS;
        }

        $completed = 0;
        $failed = 0;
        $deadLettered = 0;
        foreach ($actionIds as $actionId) {
            try {
                $consumer->consume(EconomicAction::findOrFail($actionId));
                $completed++;
            } catch (Throwable $e) {
                $current = EconomicAction::find($actionId);
                if ($current?->status === 'dead_letter') {
                    $deadLettered++;
                } else {
                    $failed++;
                }
                $this->error('Action #' . $actionId . ' failed: ' . $e->getMessage());
            }
        }

        $this->info("Consumed {$completed} governance execution action(s); {$failed} retryable failure(s); {$deadLettered} dead-lettered.");

        return ($failed + $deadLettered) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
