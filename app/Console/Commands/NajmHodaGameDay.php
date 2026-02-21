<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaAutonomyGameDayService;
use Illuminate\Console\Command;

class NajmHodaGameDay extends Command
{
    protected $signature = 'najm-hoda:gameday
        {--scenario=* : Scenario key(s) to run}
        {--dry-run : Evaluate scenarios without emitting external notifications}
        {--history=0 : Show latest N reports instead of running a new drill}';

    protected $description = 'Run Najm Hoda autonomy GameDay chaos drill scenarios';

    public function __construct(
        protected NajmHodaAutonomyGameDayService $gameDayService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. GameDay skipped.');
            return self::SUCCESS;
        }

        $history = (int) $this->option('history');
        if ($history > 0) {
            $rows = $this->gameDayService->history($history);
            if (empty($rows)) {
                $this->line('No GameDay history found.');
                return self::SUCCESS;
            }

            $this->table(
                ['Generated At', 'Status', 'Scenarios', 'Passed', 'Failed', 'DryRun'],
                array_map(static function (array $row): array {
                    return [
                        (string) ($row['generated_at'] ?? '-'),
                        (string) ($row['status'] ?? 'unknown'),
                        (int) ($row['scenario_count'] ?? 0),
                        (int) ($row['passed_count'] ?? 0),
                        (int) ($row['failed_count'] ?? 0),
                        (bool) ($row['dry_run'] ?? false) ? 'yes' : 'no',
                    ];
                }, $rows)
            );

            return self::SUCCESS;
        }

        $scenarios = (array) $this->option('scenario');
        $dryRun = (bool) $this->option('dry-run');
        $report = $this->gameDayService->run($scenarios, $dryRun);

        $this->info('GameDay report generated.');
        $this->table(
            ['Scenario', 'Passed', 'Detail'],
            array_map(static function (array $item): array {
                return [
                    (string) ($item['name'] ?? 'unknown'),
                    (bool) ($item['passed'] ?? false) ? 'yes' : 'no',
                    (string) ($item['detail'] ?? '-'),
                ];
            }, (array) ($report['results'] ?? []))
        );

        $this->line(sprintf(
            'Status=%s | scenarios=%d | passed=%d | failed=%d',
            (string) ($report['status'] ?? 'unknown'),
            (int) ($report['scenario_count'] ?? 0),
            (int) ($report['passed_count'] ?? 0),
            (int) ($report['failed_count'] ?? 0)
        ));

        return self::SUCCESS;
    }
}
