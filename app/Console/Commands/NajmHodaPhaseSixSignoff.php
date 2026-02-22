<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaPhaseSixSignoffService;
use Illuminate\Console\Command;

class NajmHodaPhaseSixSignoff extends Command
{
    protected $signature = 'najm-hoda:phase6-signoff
        {--report : Generate and persist Go/No-Go report}
        {--sign : Record executive sign-off decision}
        {--decision= : Decision value (go|conditional_go|no_go)}
        {--note= : Sign-off note}
        {--history=0 : Show latest N history rows}
        {--window=24 : Analysis window in hours}';

    protected $description = 'Generate phase-6 Go/No-Go report and record executive sign-off for Najm Hoda autonomy';

    public function __construct(
        protected NajmHodaPhaseSixSignoffService $signoffService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Phase-6 signoff skipped.');
            return self::SUCCESS;
        }

        $history = (int) $this->option('history');
        if ($history > 0) {
            $rows = $this->signoffService->history($history);
            if (empty($rows)) {
                $this->line('No signoff history found.');
                return self::SUCCESS;
            }
            $this->table(
                ['Timestamp', 'Type', 'Decision', 'By'],
                array_map(static function (array $row): array {
                    return [
                        (string) ($row['timestamp'] ?? '-'),
                        (string) ($row['type'] ?? '-'),
                        (string) ($row['decision'] ?? '-'),
                        (string) ($row['by_user_id'] ?? '-'),
                    ];
                }, $rows)
            );
            return self::SUCCESS;
        }

        $window = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
        if ((bool) $this->option('sign')) {
            $decision = (string) ($this->option('decision') ?? '');
            if ($decision === '') {
                $this->error('Decision is required for --sign. Use --decision=go|conditional_go|no_go.');
                return self::FAILURE;
            }

            $result = $this->signoffService->sign(
                $decision,
                auth()->id(),
                $this->option('note') !== null ? (string) $this->option('note') : null,
                $window
            );

            if (!(bool) ($result['success'] ?? false)) {
                $this->error('Sign-off failed: ' . (string) ($result['reason'] ?? 'unknown'));
                return self::FAILURE;
            }

            $this->info('Phase-6 sign-off recorded.');
            return $this->printStatus();
        }

        if ((bool) $this->option('report')) {
            $report = $this->signoffService->report($window, true);
            $this->line('Decision: ' . (string) ($report['decision'] ?? 'conditional_go'));
            return $this->printStatus();
        }

        return $this->printStatus();
    }

    protected function printStatus(): int
    {
        $state = $this->signoffService->status();
        $this->table(['Key', 'Value'], [
            ['last_decision', (string) ($state['last_decision'] ?? '')],
            ['last_report_at', (string) ($state['last_report_at'] ?? '')],
            ['last_signed_decision', (string) ($state['last_signed_decision'] ?? '')],
            ['last_signed_at', (string) ($state['last_signed_at'] ?? '')],
            ['last_signed_by', (string) ($state['last_signed_by'] ?? '')],
        ]);

        return self::SUCCESS;
    }
}
