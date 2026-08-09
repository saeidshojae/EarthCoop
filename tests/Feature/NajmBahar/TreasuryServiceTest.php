<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\TreasuryFund;
use App\Modules\NajmBahar\Models\TreasuryTransfer;
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
        $this->setActiveBalance($fund, 1_000);

        $fund->required_reserve = 300;
        $fund->committed_liabilities = 250;
        $fund->save();
        $fund->load('account');

        $this->assertSame(450, $fund->availableSurplus());
    }

    public function test_interfund_transfer_cannot_spend_protected_reserve_and_is_idempotent(): void
    {
        $treasury = app(TreasuryService::class);
        $source = $treasury->get(TreasuryService::IDLE_TAX);
        $destination = $treasury->get(TreasuryService::OPERATIONS_SALARY);

        $this->setActiveBalance($source, 1_000);
        $source->required_reserve = 300;
        $source->committed_liabilities = 200;
        $source->save();

        $transfer = $treasury->transferSurplus(
            TreasuryService::IDLE_TAX,
            TreasuryService::OPERATIONS_SALARY,
            400,
            'Cover approved operations shortfall',
            'tax-to-operations-2026-01',
            1,
            'treasury-policy-v1'
        );

        $this->assertSame(400, (int) $transfer->amount);
        $this->assertSame(1, TreasuryTransfer::count());
        $this->assertSame(600, (int) $source->account->fresh()->balance_active);
        $this->assertSame(400, (int) $destination->account->fresh()->balance_active);

        $replay = $treasury->transferSurplus(
            TreasuryService::IDLE_TAX,
            TreasuryService::OPERATIONS_SALARY,
            400,
            'Cover approved operations shortfall',
            'tax-to-operations-2026-01',
            1,
            'treasury-policy-v1'
        );

        $this->assertSame($transfer->id, $replay->id);
        $this->assertSame(1, TreasuryTransfer::count());
        $this->assertSame(600, (int) $source->account->fresh()->balance_active);

        $this->expectException(\RuntimeException::class);
        $treasury->transferSurplus(
            TreasuryService::IDLE_TAX,
            TreasuryService::OPERATIONS_SALARY,
            101,
            'Attempt to cross protected reserve',
            'tax-to-operations-too-much'
        );
    }

    private function setActiveBalance(TreasuryFund $fund, int $amount): void
    {
        $fund->load('account');
        $fund->account->balance_active = $amount;
        $fund->account->balance_faded = 0;
        $fund->account->balance = $amount;
        $fund->account->save();

        $sub = SubAccount::where('sub_account_code', $fund->account->account_number)->firstOrFail();
        $sub->balance_active = $amount;
        $sub->balance_faded = 0;
        $sub->balance = $amount;
        $sub->save();
    }
}
