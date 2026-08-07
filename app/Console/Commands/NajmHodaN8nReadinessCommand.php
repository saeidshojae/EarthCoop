<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Integrations\N8n\N8nReadinessService;
use Illuminate\Console\Command;

class NajmHodaN8nReadinessCommand extends Command
{
    protected $signature = 'najm-hoda:n8n-readiness {--json : Output machine-readable JSON} {--strict : Exit non-zero unless staging readiness passes}';

    protected $description = 'Report Najm Hoda n8n staging readiness without exposing secrets';

    public function handle(N8nReadinessService $readiness): int
    {
        $report = $readiness->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Najm Hoda ↔ n8n readiness: ' . strtoupper((string) $report['status']));
            $this->line('Host: ' . ((string) ($report['base_url_host'] ?? 'not configured')));
            $this->line('Cache: ' . ((string) ($report['cache_driver'] ?? 'unknown')));
            $this->line('Secret: ' . (($report['secret_configured'] ?? false) ? 'configured (hidden)' : 'not configured'));
            $this->newLine();

            foreach ((array) ($report['checks'] ?? []) as $name => $ok) {
                $this->line(sprintf('[%s] %s', $ok ? 'OK' : 'NO', $name));
            }
        }

        if ($this->option('strict') && ($report['status'] ?? null) !== 'ready') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
