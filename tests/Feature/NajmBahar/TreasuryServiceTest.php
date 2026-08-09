<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\TreasuryFund;
use App\Modules\NajmBahar\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_treasury_funds_are_registered_without_moving_money(): void
    {
        $funds = app(TreasuryService::class)->ensureDefaultFunds();

        $this->assertCount(4, $funds);
        $this->assertSame(4, TreasuryFund::count());

        foreach ([
            TreasuryService::OPERATIONS_SALARY,
            TreasuryService::CENTRAL_INSURANCE,
            TreasuryService::MONEY_DESTRUCTION,
            TreasuryService::IDLE_TAX,
        ] as $code) {
            $fund = TreasuryFund::with('account')->where('code', $code)->firstOrFail();
            $this->assertNotNull($fund->account);
            $this->assertSame(0, (int) $fund->account->balance);
            $this->assertSame(0, (int) ($fund->account->balance_active ?? 0));
            $this->assertSame(0, (int) ($fund->account->balance_faded ?? 0));
        }
    }

    public function test_available_surplus_respects_reserve_and_committed_liabilities(): void
    {
        $fund = app(TreasuryService::class)->get(TreasuryService::MONEY_DESTRUCTION);
        $fund->account->balance_active = 1_000;
        $fund->account->balance = 1_000;
        $fund->account->save();

        $fund->required_reserve = 300;
        $fund->committed_liabilities = 250;
        $fund->save();
        $fund->load('account');

        $this->assertSame(450, $fund->availableSurplus());
    }
}
