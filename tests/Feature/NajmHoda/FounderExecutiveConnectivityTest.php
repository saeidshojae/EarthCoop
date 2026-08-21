<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use App\Services\NajmHoda\FounderOps\FounderStockDecisionService;
use Mockery;
use Tests\TestCase;

class FounderExecutiveConnectivityTest extends TestCase
{
    public function test_connectivity_report_distinguishes_real_handlers_from_policy_only_actions(): void
    {
        $connected=[
            'runtime_health.collect_health_snapshot','runtime_health.classify_incident','runtime_health.run_read_only_diagnostic',
            'support.classify_ticket','support.assign_priority','support.draft_reply',
            'groups.summarize_activity','groups.propose_action_item',
            'governance.summarize_election','governance.flag_anomaly',
            'invitations.summarize_growth','admin_settings.audit_configuration',
            'reports_moderation.prepare_case_summary','reports_moderation.classify_report',
            'secretariat.prepare_follow_up','stock.summarize_auction','stock.flag_settlement_issue',
            'najm_bahar.summarize_financial_state','najm_bahar.flag_transaction_anomaly',
        ];
        $lowRisk=Mockery::mock(FounderLowRiskDomainActionService::class);
        $lowRisk->shouldReceive('supports')->andReturnUsing(static fn(string $domain,string $action): bool=>in_array($domain.'.'.$action,$connected,true));

        $report=(new FounderExecutiveConnectivityService($lowRisk))->report();

        $this->assertSame(17,$report['summary']['domains']);
        $this->assertSame(17,$report['summary']['read_connected']);
        $this->assertSame('connected',$report['domains']['groups']['actions']['summarize_activity']['state']);
        $this->assertSame('connected',$report['domains']['governance']['actions']['flag_anomaly']['state']);
        $this->assertSame('connected',$report['domains']['stock']['actions']['settle_auction']['state']);
        $this->assertSame(FounderStockDecisionService::class,$report['domains']['stock']['actions']['settle_auction']['adapter']);
        $this->assertSame('protected',$report['domains']['stock']['actions']['alter_ownership_history']['state']);
        $this->assertGreaterThan(0,$report['summary']['missing_executable_actions']);
        $this->assertNotEmpty($report['rollout_queue']);
    }
}
