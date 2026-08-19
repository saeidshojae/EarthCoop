<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderCapabilityGate;
use Tests\TestCase;

class FounderCapabilityGateTest extends TestCase
{
    public function test_unknown_actions_fail_closed(): void
    {
        $gate = app(FounderCapabilityGate::class);
        $decision = $gate->inspect('najm_bahar', 'totally_unknown_action', true, true);

        $this->assertSame(FounderCapabilityGate::FORBIDDEN, $decision['level']);
        $this->assertFalse($decision['allowed']);
        $this->assertFalse($gate->canExecute('najm_bahar', 'totally_unknown_action', true, true));
    }

    public function test_financial_execution_requires_explicit_founder_approval(): void
    {
        $gate = app(FounderCapabilityGate::class);

        $withoutApproval = $gate->inspect('najm_bahar', 'execute_transaction');
        $withApproval = $gate->inspect('najm_bahar', 'execute_transaction', true);

        $this->assertSame(FounderCapabilityGate::APPROVAL_REQUIRED, $withoutApproval['level']);
        $this->assertFalse($withoutApproval['allowed']);
        $this->assertTrue($withApproval['allowed']);
        $this->assertTrue($gate->canExecute('najm_bahar', 'execute_transaction', true));
    }

    public function test_delegated_safe_action_requires_an_explicit_grant(): void
    {
        $gate = app(FounderCapabilityGate::class);

        $this->assertFalse($gate->canExecute('support', 'classify'));
        $this->assertTrue($gate->canExecute('support', 'classify', false, true));
    }

    public function test_observe_and_propose_never_count_as_executable_mutations(): void
    {
        $gate = app(FounderCapabilityGate::class);

        $this->assertTrue($gate->inspect('support', 'view')['allowed']);
        $this->assertFalse($gate->canExecute('support', 'view'));

        $this->assertTrue($gate->inspect('email', 'draft_message')['allowed']);
        $this->assertFalse($gate->canExecute('email', 'draft_message'));
    }

    public function test_irreversible_history_rewrites_remain_forbidden_even_with_approval_and_delegation(): void
    {
        $gate = app(FounderCapabilityGate::class);

        foreach ([
            ['najm_bahar', 'rewrite_ledger'],
            ['stock', 'change_ownership_history'],
            ['secretariat', 'rewrite_history'],
            ['governance', 'alter_vote_or_result'],
        ] as [$domain, $action]) {
            $decision = $gate->inspect($domain, $action, true, true);
            $this->assertTrue($decision['forbidden']);
            $this->assertFalse($decision['allowed']);
            $this->assertFalse($gate->canExecute($domain, $action, true, true));
        }
    }
}
