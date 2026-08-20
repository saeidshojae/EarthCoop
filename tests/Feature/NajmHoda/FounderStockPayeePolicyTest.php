<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderActionAuthorityService;
use Tests\TestCase;

class FounderStockPayeePolicyTest extends TestCase
{
    public function test_stock_payee_configuration_requires_founder_approval(): void
    {
        $authority=app(FounderActionAuthorityService::class);
        $decision=$authority->inspect('stock','configure_payee_account');

        $this->assertSame('approval_required',$decision['mode'] ?? null);
        $this->assertFalse((bool)($decision['executable_now'] ?? false));
    }
}
