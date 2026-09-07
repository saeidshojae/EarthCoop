<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveReservationCommittedDimInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlement_and_refund_preserve_committed_dim_in_stored_account_total(): void
    {
        $payer = Account::create([
            'account_number' => '9400000001',
            'name' => 'Reservation payer with committed dim',
            'type' => 'user',
            'balance' => 1_300,
            'balance_active' => 1_000,
            'balance_faded' => 100,
            'committed_dim' => 200,
            'status' => 1,
        ]);

        $payee = Account::create([
            'account_number' => '9400000002',
            'name' => 'Reservation payee with committed dim',
            'type' => 'user',
            'balance' => 120,
            'balance_active' => 0,
            'balance_faded' => 50,
            'committed_dim' => 70,
            'status' => 1,
        ]);

        $service = app(ActiveBaharReservationService::class);
        $service->reserve($payer->account_number, 400, 'committed-reserve-1', 'uat', 1);
        $service->settle('committed-reserve-1', $payee->account_number, 'committed-settle-1');

        $payer->refresh();
        $payee->refresh();

        $this->assertSame(200, (int) $payer->committed_dim);
        $this->assertSame(900, (int) $payer->balance);
        $this->assertSame(70, (int) $payee->committed_dim);
        $this->assertSame(520, (int) $payee->balance);

        $service->refund('committed-reserve-1', 150, 'committed-refund-1');

        $payer->refresh();
        $payee->refresh();

        $this->assertSame(200, (int) $payer->committed_dim);
        $this->assertSame(1_050, (int) $payer->balance);
        $this->assertSame(70, (int) $payee->committed_dim);
        $this->assertSame(370, (int) $payee->balance);
    }
}
