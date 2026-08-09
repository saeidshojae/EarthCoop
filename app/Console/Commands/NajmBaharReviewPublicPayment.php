<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\Governance\Services\PublicExecutionPaymentApprovalService;
use Illuminate\Console\Command;
use Throwable;

class NajmBaharReviewPublicPayment extends Command
{
    protected $signature = 'najm-bahar:review-public-payment
        {instruction : Public execution payment instruction ID}
        {actor : Reviewing user ID}
        {--approve : Approve the instruction as the required second authority}
        {--cancel= : Cancel the instruction with the supplied reason}';

    protected $description = 'Explicitly approve or cancel a public execution payment instruction before Najm Bahar execution.';

    public function handle(PublicExecutionPaymentApprovalService $service): int
    {
        $approve = (bool) $this->option('approve');
        $cancelReason = $this->option('cancel');

        if (($approve && $cancelReason !== null) || (! $approve && $cancelReason === null)) {
            $this->error('Choose exactly one of --approve or --cancel="reason".');
            return self::INVALID;
        }

        try {
            $instruction = PublicExecutionPaymentInstruction::findOrFail((int) $this->argument('instruction'));
            $actor = User::findOrFail((int) $this->argument('actor'));

            $reviewed = $approve
                ? $service->approve($instruction, $actor)
                : $service->cancel($instruction, $actor, (string) $cancelReason);

            $this->info('Payment instruction #' . $reviewed->id . ' is now ' . $reviewed->status . '.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
