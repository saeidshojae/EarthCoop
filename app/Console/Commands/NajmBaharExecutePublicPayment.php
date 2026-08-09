<?php

namespace App\Console\Commands;

use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\NajmBahar\Services\PublicExecutionPaymentService;
use Illuminate\Console\Command;
use Throwable;

class NajmBaharExecutePublicPayment extends Command
{
    protected $signature = 'najm-bahar:execute-public-payment
        {instruction : Public execution payment instruction ID}
        {--retry-failed : Explicitly retry a failed payment}
        {--recover-dead-letter : Explicitly recover a dead-letter payment before retrying}';

    protected $description = 'Execute one explicit public-project payment instruction inside the Najm Bahar monetary boundary.';

    public function handle(PublicExecutionPaymentService $payments): int
    {
        $instructionId = (int) $this->argument('instruction');
        if ($instructionId <= 0) {
            $this->error('A positive payment instruction ID is required.');
            return self::FAILURE;
        }

        try {
            $instruction = PublicExecutionPaymentInstruction::findOrFail($instructionId);

            if ($this->option('recover-dead-letter')) {
                $instruction = $payments->recoverDeadLetter($instruction);
                $this->warn('Recovered dead-letter public payment instruction #' . $instruction->id . '.');
            }

            $executed = $payments->execute($instruction, (bool) $this->option('retry-failed'));
            $transactionId = (int) (($executed->metadata ?? [])['payment_transaction_id'] ?? 0);
            $this->info('Executed public payment instruction #' . $executed->id . ' with transaction #' . $transactionId . '.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $fresh = PublicExecutionPaymentInstruction::find($instructionId);
            if ($fresh && in_array($fresh->status, ['failed', 'dead_letter'], true)) {
                $this->warn(
                    'Instruction #' . $fresh->id . ' is now ' . $fresh->status
                    . ' after ' . (int) $fresh->attempts . ' attempt(s); operator attention is required.'
                );
            }
            $this->error('Public payment execution failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
