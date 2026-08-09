<?php

namespace App\Console\Commands;

use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\NajmBahar\Services\PublicExecutionPaymentService;
use Illuminate\Console\Command;
use Throwable;

class NajmBaharExecutePublicPayment extends Command
{
    protected $signature = 'najm-bahar:execute-public-payment {instruction : Public execution payment instruction ID}';

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
            $executed = $payments->execute($instruction);
            $transactionId = (int) (($executed->metadata ?? [])['payment_transaction_id'] ?? 0);
            $this->info('Executed public payment instruction #' . $executed->id . ' with transaction #' . $transactionId . '.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Public payment execution failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
