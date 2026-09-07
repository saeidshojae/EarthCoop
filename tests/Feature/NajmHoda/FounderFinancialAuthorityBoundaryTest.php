<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderActionAuthorityService;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use Tests\TestCase;

class FounderFinancialAuthorityBoundaryTest extends TestCase
{
    public function test_stock_and_bahar_mutations_are_not_delegated_safe(): void
    {
        $authority=app(FounderActionAuthorityService::class);

        foreach ([
            ['stock','create_auction'],['stock','settle_auction'],['stock','transfer_shares'],
            ['najm_bahar','approve_project'],['najm_bahar','execute_transaction'],['najm_bahar','change_monetary_policy'],
        ] as [$domain,$action]) {
            $this->assertSame('approval_required',$authority->mode($domain,$action));
            $this->assertFalse($authority->mayExecute($domain,$action));
        }

        $this->assertSame('forbidden',$authority->mode('stock','alter_ownership_history'));
        $this->assertSame('forbidden',$authority->mode('najm_bahar','alter_ledger_history'));
    }

    public function test_low_risk_executor_exposes_audits_not_financial_mutations(): void
    {
        $actions=app(FounderLowRiskDomainActionService::class);

        $this->assertTrue($actions->supports('stock','flag_settlement_issue'));
        $this->assertTrue($actions->supports('najm_bahar','flag_transaction_anomaly'));
        $this->assertFalse($actions->supports('stock','settle_auction'));
        $this->assertFalse($actions->supports('stock','transfer_shares'));
        $this->assertFalse($actions->supports('najm_bahar','execute_transaction'));
    }
}
