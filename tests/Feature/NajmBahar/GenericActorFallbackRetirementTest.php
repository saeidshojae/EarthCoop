<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\StrictTransactionService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenericActorFallbackRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_main_actor_transfer_fails_closed_without_mutation(): void
    {
        $service = app(TransactionService::class);
        $this->assertInstanceOf(StrictTransactionService::class, $service);

        $from = Account::create([
            'account_number' => 'LEGAL-ENTITY-001',
            'name' => 'Legal entity A',
            'type' => 'legal_entity',
            'balance' => 100,
            'balance_active' => 100,
            'balance_faded' => 0,
        ]);

        $to = Account::create([
            'account_number' => 'LEGAL-ENTITY-002',
            'name' => 'Legal entity B',
            'type' => 'legal_entity',
            'balance' => 10,
            'balance_active' => 10,
            'balance_faded' => 0,
        ]);

        try {
            $service->transfer(
                $from->account_number,
                $to->account_number,
                25,
                'Forbidden generic actor transfer',
                [],
                'release-d-generic-actor-fallback',
                'active'
            );
            $this->fail('Generic actor transfer unexpectedly reached the legacy transaction fallback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('explicit canonical transfer boundary', $exception->getMessage());
        }

        $this->assertSame(100, (int) $from->fresh()->balance_active);
        $this->assertSame(100, (int) $from->fresh()->balance);
        $this->assertSame(10, (int) $to->fresh()->balance_active);
        $this->assertSame(10, (int) $to->fresh()->balance);
    }
}
