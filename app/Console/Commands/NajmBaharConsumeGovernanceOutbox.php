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
        {--retry-failed : Include previously failed actions}';

    protected $description = 'Consume authorized governance execution outbox actions inside the Najm Bahar monetary boundary.';

    public function handle(GovernanceExecutionOutboxConsumer $consumer): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $statuses = $this->option('retry-failed') ? ['pending', 'failed'] : ['pending'];

        $actionIds = EconomicAction::query()
            ->where('action_type', PublicExecutionBridge::PUBLIC_PROJECT_EXECUTION_AUTHORIZED)
            ->whereIn('status', $statuses)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($actionIds->isEmpty()) {
            $this->info('No governance execution outbox actions are ready for Najm Bahar consumption.');
            return self::SUCCESS;
        }

        $completed = 0;
        $failed = 0;
        foreach ($actionIds as $actionId) {
            try {
                $consumer->consume(EconomicAction::findOrFail($actionId));
                $completed++;
            } catch (Throwable $e) {
                $failed++;
                $this->error('Action #' . $actionId . ' failed: ' . $e->getMessage());
            }
        }

        $this->info("Consumed {$completed} governance execution action(s); {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
