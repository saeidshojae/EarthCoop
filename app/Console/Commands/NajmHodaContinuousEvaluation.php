<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaContinuousEvaluationHarnessService;
use Illuminate\Console\Command;

class NajmHodaContinuousEvaluation extends Command
{
    protected $signature = 'najm-hoda:continuous-evaluation
        {--window=24 : Evaluation window in hours}
        {--dry-run : Do not store report and do not notify}
        {--history=0 : Show latest N reports}
        {--alerts-history=0 : Show latest N alert rows}
        {--fail-on-breach : Return non-zero exit code if status is breach}';

    protected $description = 'Run nightly continuous evaluation harness for autonomy quality and safety regression';

    public function __construct(
        protected NajmHodaContinuousEvaluationHarnessService $evaluationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Continuous evaluation skipped.');
            return self::SUCCESS;
        }

        $history = (int) $this->option('history');
        if ($history > 0) {
            $rows = $this->evaluationService->history($history);
            if (empty($rows)) {
                $this->line('No evaluation history found.');
                return self::SUCCESS;
            }

            $this->table(
                ['Generated At', 'Status', 'Quality', 'Safety', 'Drift', 'Alerts'],
                array_map(static function (array $row): array {
                    return [
                        (string) ($row['generated_at'] ?? '-'),
                        (string) ($row['status'] ?? 'unknown'),
                        (string) data_get($row, 'decision_quality.score', 0),
                        (string) data_get($row, 'safety_regression.status', 'ok'),
                        (string) data_get($row, 'drift_trend.status', 'ok'),
                        (string) ($row['alert_count'] ?? 0),
                    ];
                }, $rows)
            );

            return self::SUCCESS;
        }

        $alertsHistory = (int) $this->option('alerts-history');
        if ($alertsHistory > 0) {
            $rows = $this->evaluationService->alertsHistory($alertsHistory);
            if (empty($rows)) {
                $this->line('No evaluation alerts history found.');
                return self::SUCCESS;
            }

            $this->table(
                ['Raised At', 'Code', 'Severity', 'Source', 'Value'],
                array_map(static function (array $row): array {
                    return [
                        (string) ($row['raised_at'] ?? '-'),
                        (string) ($row['code'] ?? '-'),
                        (string) ($row['severity'] ?? '-'),
                        (string) ($row['source'] ?? '-'),
                        (string) ($row['value'] ?? '-'),
                    ];
                }, $rows)
            );

            return self::SUCCESS;
        }

        $window = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
        $dryRun = (bool) $this->option('dry-run');
        $report = $this->evaluationService->run($window, $dryRun);

        $this->table(['Key', 'Value'], [
            ['status', (string) ($report['status'] ?? 'unknown')],
            ['window_hours', (string) ($report['window_hours'] ?? 24)],
            ['decision_quality_score', (string) data_get($report, 'decision_quality.score', 0)],
            ['safety_regression_status', (string) data_get($report, 'safety_regression.status', 'ok')],
            ['drift_status', (string) data_get($report, 'drift_trend.status', 'ok')],
            ['drift_trend', (string) data_get($report, 'drift_trend.trend', 'stable')],
            ['alert_count', (string) ($report['alert_count'] ?? 0)],
        ]);

        if ((bool) $this->option('fail-on-breach') && (string) ($report['status'] ?? 'ok') === 'breach') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

