<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderActionAuthorityService;
use Tests\TestCase;

class FounderActionAuthorityServiceTest extends TestCase
{
    public function test_unknown_actions_fail_closed(): void
    {
        $decision = app(FounderActionAuthorityService::class)->evaluate('najm_bahar', 'invent_money');

        $this->assertFalse($decision['known_action']);
        $this->assertSame('forbidden', $decision['mode']);
        $this->assertFalse($decision['may_execute']);
    }

    public function test_forbidden_action_cannot_execute_even_with_founder_approval_flag(): void
    {
        $service = app(FounderActionAuthorityService::class);

        $this->assertFalse($service->mayExecute('najm_bahar', 'alter_ledger_history', true));
        $this->assertFalse($service->mayExecute('governance', 'alter_vote', true));
        $this->assertFalse($service->mayExecute('stock', 'alter_ownership_history', true));
    }

    public function test_approval_required_action_executes_only_after_explicit_approval(): void
    {
        $service = app(FounderActionAuthorityService::class);

        $before = $service->evaluate('email', 'send_email', false);
        $after = $service->evaluate('email', 'send_email', true);

        $this->assertSame('approval_required', $before['mode']);
        $this->assertTrue($before['requires_founder_approval']);
        $this->assertFalse($before['may_execute']);
        $this->assertFalse($after['requires_founder_approval']);
        $this->assertTrue($after['may_execute']);
    }

    public function test_delegated_safe_is_not_executable_until_explicit_grant_is_supplied(): void
    {
        $service = app(FounderActionAuthorityService::class);

        $this->assertFalse($service->mayExecute('support', 'classify_ticket'));
        $this->assertTrue($service->mayExecute('support', 'classify_ticket', false, true));
        $this->assertFalse($service->mayExecute('governance', 'alter_vote', false, true));
    }

    public function test_proposal_mode_never_mutates(): void
    {
        $decision = app(FounderActionAuthorityService::class)->evaluate('admin_settings', 'recommend_change', true);

        $this->assertSame('propose', $decision['mode']);
        $this->assertTrue($decision['may_prepare']);
        $this->assertFalse($decision['may_execute']);
    }
}
