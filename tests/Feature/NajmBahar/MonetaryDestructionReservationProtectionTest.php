<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetaryDestructionReservationProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_destruction_can_only_consume_unreserved_active_bahar(): void
    {
        $fundAccount = Account::create([
            'account_number' => '0000000000-990',
            'name' => 'UAT destruction fund',
            'type' => 'system',
            'balance' => 1_000,
            'balance_active' => 1_000,
            'balance_faded' => 0,
            'committed_dim' => 0,
            'status' => 1,
        ]);

        $reservations = app(ActiveBaharReservationService::class);
        $reservations->reserve(
            $fundAccount->account_number,
            800,
            'uat:destruction-reserved-active',
            'treasury_obligation',
            1
        );

        $result = app(MonetaryService::class)->destroyActive(
            $fundAccount,
            500,
            'UAT destruction must preserve reservation backing',
            ['type' => 'uat_reserved_destruction'],
            'uat-destruction-reservation-protection',
            true
        );

        $fundAccount->refresh();

        $this->assertTrue($result['applied']);
        $this->assertSame(200, (int) $result['amount']);
        $this->assertSame(800, (int) $fundAccount->balance_active);
        $this->assertSame(800, (int) $fundAccount->balance);
        $this->assertSame(0, $reservations->availableActive($fundAccount));
        $this->assertSame(1, Transaction::where('metadata->idempotency_key', 'uat-destruction-reservation-protection')->count());
    }
}
