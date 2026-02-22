<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaOperationalAutonomyActivationService;
use Illuminate\Console\Command;

class NajmHodaOpsActivation extends Command
{
    protected $signature = 'najm-hoda:ops-activation
        {--activate : Activate 24/7 operations mode}
        {--deactivate : Deactivate 24/7 operations mode}
        {--tick : Run one virtual shift tick}
        {--status : Show current operations mode state}
        {--history=0 : Show latest N history rows}
        {--mode=night_only : Activation mode (night_only|always)}
        {--window=24 : Evaluation window in hours for tick}
        {--reason= : Optional reason}
        {--manual : Force tick even outside virtual shift window}';

    protected $description = 'Control and run Najm Hoda 24/7 operational autonomy virtual shifts';

    public function __construct(
        protected NajmHodaOperationalAutonomyActivationService $opsActivationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. 24/7 operations activation skipped.');
            return self::SUCCESS;
        }

        $history = (int) $this->option('history');
        if ($history > 0) {
            $rows = $this->opsActivationService->history($history);
            if (empty($rows)) {
                $this->line('No operations activation history found.');
                return self::SUCCESS;
            }

            $this->table(
                ['Timestamp', 'Type', 'Status', 'Reason'],
                array_map(static function (array $row): array {
                    return [
                        (string) ($row['timestamp'] ?? '-'),
                        (string) ($row['type'] ?? '-'),
                        (string) ($row['status'] ?? '-'),
                        (string) ($row['reason'] ?? (string) ($row['evaluation_status'] ?? '-')),
                    ];
                }, $rows)
            );

            return self::SUCCESS;
        }

        $reason = $this->option('reason') !== null ? (string) $this->option('reason') : null;
        if ((bool) $this->option('activate')) {
            $result = $this->opsActivationService->activate(auth()->id(), (string) $this->option('mode'), $reason);
            if (!(bool) ($result['success'] ?? false)) {
                $this->error('Activation failed: ' . (string) ($result['reason'] ?? 'unknown'));
                return self::FAILURE;
            }
            $this->info('24/7 operations mode activated.');
            return $this->printStatus();
        }

        if ((bool) $this->option('deactivate')) {
            $result = $this->opsActivationService->deactivate(auth()->id(), $reason ?: 'manual_deactivation');
            if (!(bool) ($result['success'] ?? false)) {
                $this->error('Deactivation failed.');
                return self::FAILURE;
            }
            $this->info('24/7 operations mode deactivated.');
            return $this->printStatus();
        }

        if ((bool) $this->option('tick')) {
            $window = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
            $result = $this->opsActivationService->tick(auth()->id(), (bool) $this->option('manual'), $window);
            $this->line('Tick status: ' . (string) ($result['status'] ?? 'unknown'));
            if ((bool) ($result['halted'] ?? false)) {
                $this->warn('Operations mode halted by safe-stop threshold.');
                return self::FAILURE;
            }
            return self::SUCCESS;
        }

        return $this->printStatus();
    }

    protected function printStatus(): int
    {
        $state = $this->opsActivationService->status();
        $this->table(['Key', 'Value'], [
            ['status', (string) ($state['status'] ?? 'inactive')],
            ['mode', (string) ($state['mode'] ?? 'night_only')],
            ['activated_at', (string) ($state['activated_at'] ?? '')],
            ['last_tick_at', (string) ($state['last_tick_at'] ?? '')],
            ['last_tick_status', (string) ($state['last_tick_status'] ?? '')],
            ['consecutive_breach_count', (string) ($state['consecutive_breach_count'] ?? 0)],
            ['halted_reason', (string) ($state['halted_reason'] ?? '')],
        ]);

        return self::SUCCESS;
    }
}

