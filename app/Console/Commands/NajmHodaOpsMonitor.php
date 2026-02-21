<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaOpsHealthMonitor;
use App\Services\NajmHoda\Runtime\NajmHodaOpsEscalationService;
use App\Services\NajmHoda\Runtime\NajmHodaOpsRetentionService;
use App\Services\NajmHoda\Runtime\NajmHodaOpsTriageService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NajmHodaOpsMonitor extends Command
{
    protected $signature = 'najm-hoda:ops-monitor
        {--window= : Health window in minutes}
        {--limit= : Number of recent runtime events to inspect}
        {--skip-retention : Skip retention and cleanup pass}
        {--dry-run : Do not apply auto playbooks}';

    protected $description = 'Run Najm Hoda operational health monitoring and triage';

    public function __construct(
        protected NajmHodaOpsHealthMonitor $healthMonitor,
        protected NajmHodaOpsTriageService $triageService,
        protected NajmHodaOpsEscalationService $escalationService,
        protected NajmHodaOpsRetentionService $retentionService,
        protected RuntimeEventBus $eventBus
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Ops monitor skipped.');
            return self::SUCCESS;
        }

        $window = $this->option('window');
        $limit = $this->option('limit');
        $skipRetention = (bool) $this->option('skip-retention');
        $dryRun = (bool) $this->option('dry-run');

        $windowMinutes = is_numeric($window) ? (int) $window : null;
        $recentLimit = is_numeric($limit) ? (int) $limit : null;

        $snapshot = $this->healthMonitor->snapshot($windowMinutes, $recentLimit);
        $incidents = $this->triageService->processSnapshot($snapshot, !$dryRun);
        $escalations = $this->escalationService->escalate($snapshot, $incidents, $dryRun);
        $retention = $skipRetention ? ['history_trimmed' => 0, 'telemetry_keys_pruned' => 0] : $this->retentionService->prune();
        $this->emitRunSummary($snapshot, $incidents, $escalations, $dryRun);

        $this->line('Najm Hoda Ops Snapshot');
        $this->table(
            ['Status', 'Window (min)', 'Error %', 'Unresolved', 'Incidents'],
            [[
                (string) ($snapshot['status'] ?? 'unknown'),
                (string) ($snapshot['window_minutes'] ?? 0),
                (string) data_get($snapshot, 'metrics.error_rate_percent', 0),
                (string) data_get($snapshot, 'metrics.unresolved_requests', 0),
                (string) count($incidents) . ' / ' . count($escalations) . ' escalations',
            ]]
        );

        if (!empty($incidents)) {
            $this->line('Detected incidents:');
            foreach ($incidents as $incident) {
                $this->line(sprintf(
                    '- [%s] %s (%s)',
                    (string) ($incident['severity'] ?? 'unknown'),
                    (string) ($incident['title'] ?? 'unknown'),
                    (string) ($incident['code'] ?? 'N/A')
                ));
            }
        }

        if (!empty($escalations)) {
            $this->line('Escalation actions:');
            foreach ($escalations as $escalation) {
                $this->line(sprintf(
                    '- [%s] %s',
                    (string) ($escalation['action'] ?? 'unknown'),
                    (string) ($escalation['incident_code'] ?? 'OPS_UNKNOWN')
                ));
            }
        }

        if (!$skipRetention) {
            $this->line(sprintf(
                'Retention: history_trimmed=%d, telemetry_keys_pruned=%d',
                (int) ($retention['history_trimmed'] ?? 0),
                (int) ($retention['telemetry_keys_pruned'] ?? 0)
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $incidents
     * @param array<int, array<string, mixed>> $escalations
     */
    protected function emitRunSummary(array $snapshot, array $incidents, array $escalations, bool $dryRun): void
    {
        $runId = (string) Str::uuid();
        $summary = [
            'run_id' => $runId,
            'status' => (string) ($snapshot['status'] ?? 'unknown'),
            'window_minutes' => (int) ($snapshot['window_minutes'] ?? 0),
            'error_rate_percent' => (float) data_get($snapshot, 'metrics.error_rate_percent', 0),
            'unresolved_requests' => (int) data_get($snapshot, 'metrics.unresolved_requests', 0),
            'incident_count' => count($incidents),
            'incident_codes' => array_values(array_unique(array_map(
                static fn (array $incident): string => (string) ($incident['code'] ?? 'OPS_UNKNOWN'),
                $incidents
            ))),
            'escalation_count' => count($escalations),
            'escalation_actions' => array_values(array_unique(array_map(
                static fn (array $item): string => (string) ($item['action'] ?? 'unknown'),
                $escalations
            ))),
            'dry_run' => $dryRun,
            'generated_at' => now()->toIso8601String(),
        ];

        $this->eventBus->emit('najm_hoda.ops.run.summary', $summary);

        $ttlMinutes = max(30, (int) config('najm-hoda.runtime.ops.monitor.summary_ttl_minutes', 180));
        Cache::put('najm_hoda:ops:last_run_summary', $summary, now()->addMinutes($ttlMinutes));

        $historySize = max(1, (int) config('najm-hoda.runtime.ops.monitor.summary_history_size', 50));
        $history = Cache::get('najm_hoda:ops:run_summary_history', []);
        if (!is_array($history)) {
            $history = [];
        }
        array_unshift($history, $summary);
        $history = array_slice($history, 0, $historySize);
        Cache::put('najm_hoda:ops:run_summary_history', $history, now()->addMinutes($ttlMinutes));
    }
}
