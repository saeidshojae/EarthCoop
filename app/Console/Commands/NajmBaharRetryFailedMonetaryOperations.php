<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Services\MonetaryRetryCoordinator;
use Illuminate\Console\Command;

class NajmBaharRetryFailedMonetaryOperations extends Command
{
    protected $signature = 'najm-bahar:retry-failed-monetary-operations
        {--limit=20 : Maximum due failed operations to retry in one run}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Retry only due failed monetary operations using bounded backoff; dead-letter recovery is never automatic.';

    public function handle(MonetaryRetryCoordinator $coordinator): int
    {
        $limit = max(1, min((int) $this->option('limit'), 100));
        $result = $coordinator->retryDue($limit);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Retried %d due operations: %d completed, %d still failed, %d dead-lettered.',
            $result['attempted'],
            $result['completed'],
            $result['failed'],
            $result['dead_letter']
        ));

        return self::SUCCESS;
    }
}
