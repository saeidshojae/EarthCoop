<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacySubAccountMutationRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_base_service_cannot_execute_legacy_money_mutations(): void
    {
        $service = new SubAccountService();

        foreach ([
            fn () => $service->transferToSubAccount(1, 2, 10, 'legacy main to sub', 'active'),
            fn () => $service->transferFromSubAccount(2, 1, 10, 'legacy sub to main', 'active'),
            fn () => $service->transferBetweenSubAccounts(2, 3, 10, 'legacy sub to sub', 'active'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Retired legacy monetary mutation unexpectedly executed.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('removed in Release C', $exception->getMessage());
            }
        }
    }
}
