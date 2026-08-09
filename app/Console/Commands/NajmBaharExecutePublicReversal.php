<?php

namespace App\Console\Commands;

use App\Modules\Governance\Models\PublicExecutionReversalRequest;
use App\Modules\NajmBahar\Services\PublicExecutionReversalService;
use Illuminate\Console\Command;
use Throwable;

class NajmBaharExecutePublicReversal extends Command
{
    protected $signature = 'najm-bahar:execute-public-reversal
        {request : Public execution reversal request ID}
        {--retry-failed : Explicitly retry a failed reversal}
        {--recover-dead-letter : Recover a dead-letter reversal before executing it}';

    protected $description = 'Execute one approved public-payment reversal inside the Najm Bahar monetary boundary.';

    public function handle(PublicExecutionReversalService $reversals): int
    {
        $requestId = (int) $this->argument('request');
        if ($requestId <= 0) {
            $this->error('A positive reversal request ID is required.');
            return self::FAILURE;
        }

        try {
            $request = PublicExecutionReversalRequest::findOrFail($requestId);
            if ((bool) $this->option('recover-dead-letter')) {
                $request = $reversals->recoverDeadLetter($request);
            }

            $executed = $reversals->execute(
                $request,
                (bool) $this->option('retry-failed') || (bool) $this->option('recover-dead-letter')
            );
            $transactionId = (int) (($executed->metadata ?? [])['reversal_transaction_id'] ?? 0);
            $this->info('Executed public reversal request #' . $executed->id . ' with transaction #' . $transactionId . '.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Public reversal execution failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
