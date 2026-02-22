<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaUnifiedDomainKnowledgeGraphService;
use Illuminate\Console\Command;

class NajmHodaGraphQuery extends Command
{
    protected $signature = 'najm-hoda:graph-query
        {--actor= : Actor user id}
        {--scope= : Query scope (global|actor|group:ID)}
        {--profile=overview : Query profile (overview|member_support|project_delivery|ops_triage)}
        {--limit= : Max nodes/events per domain}';

    protected $description = 'Run unified domain knowledge graph query with RBAC-aware scope';

    public function __construct(
        protected NajmHodaUnifiedDomainKnowledgeGraphService $graphService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Graph query skipped.');
            return self::SUCCESS;
        }

        $actor = is_numeric($this->option('actor')) ? (int) $this->option('actor') : null;
        $scope = trim((string) ($this->option('scope') ?? 'actor'));
        $profile = trim((string) ($this->option('profile') ?? 'overview'));
        $limit = is_numeric($this->option('limit')) ? (int) $this->option('limit') : 20;

        $result = $this->graphService->query([
            'actor_id' => $actor,
            'scope' => $scope,
            'profile' => $profile,
            'limit' => $limit,
        ]);

        $trace = is_array($result['trace'] ?? null) ? $result['trace'] : [];
        $nodes = is_array($result['nodes'] ?? null) ? $result['nodes'] : [];

        $this->line('Najm Hoda Unified Domain Graph');
        $this->table(['Key', 'Value'], [
            ['trace_id', (string) ($trace['trace_id'] ?? '')],
            ['requested_scope', (string) ($trace['requested_scope'] ?? '')],
            ['effective_scope', (string) ($trace['effective_scope'] ?? '')],
            ['query_profile', (string) ($trace['query_profile'] ?? '')],
            ['scope_reduced_by_rbac', ((bool) ($trace['scope_reduced_by_rbac'] ?? false)) ? 'yes' : 'no'],
            ['actor_id', (string) ($trace['actor_id'] ?? '')],
        ]);

        $this->table(['Domain', 'Count'], [
            ['users', (string) count((array) ($nodes['users'] ?? []))],
            ['groups', (string) count((array) ($nodes['groups'] ?? []))],
            ['projects', (string) count((array) ($nodes['projects'] ?? []))],
            ['tickets', (string) count((array) ($nodes['tickets'] ?? []))],
            ['runtime_signals', (string) count((array) ($nodes['runtime_signals'] ?? []))],
            ['edges', (string) count((array) ($result['edges'] ?? []))],
        ]);

        $patterns = is_array($result['patterns'] ?? null) ? $result['patterns'] : [];
        $this->table(['Pattern', 'Count'], [
            ['support_escalation_candidates', (string) count((array) ($patterns['support_escalation_candidates'] ?? []))],
            ['project_delivery_risk_hotspots', (string) count((array) ($patterns['project_delivery_risk_hotspots'] ?? []))],
            ['ops_alert_chains', (string) count((array) ($patterns['ops_alert_chains'] ?? []))],
        ]);

        return self::SUCCESS;
    }
}
