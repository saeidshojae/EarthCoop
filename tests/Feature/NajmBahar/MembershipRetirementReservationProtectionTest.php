<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\NajmBahar\Services\MembershipRetirementService;
use App\Modules\NajmBahar\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipRetirementReservationProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retirement_skips_reserved_burn_fund_active_and_continues_with_idle_tax_fund(): void
    {
        $user = User::factory()->create();
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $main->balance_faded = 500_000;
        $main->balance = 500_000;
        $main->save();

        $treasury = app(TreasuryService::class);
        $burn = $treasury->get(TreasuryService::MONEY_DESTRUCTION);
        $idle = $treasury->get(TreasuryService::IDLE_TAX);

        $this->setFundActiveBalance($burn->account, 400_000);
        $this->setFundActiveBalance($idle->account, 400_000);

        $reservation = app(ActiveBaharReservationService::class)->reserve(
            $burn->account->account_number,
            300_000,
            'retirement-burn-protected-reservation',
            'test_obligation',
            1
        );

        $retirement = app(MembershipRetirementService::class)->retire($user->id, 'death');

        $this->assertSame(500_000, (int) $retirement->dim_cancelled);
        $this->assertSame(100_000, (int) $retirement->active_destroyed_from_burn_fund);
        $this->assertSame(400_000, (int) $retirement->active_destroyed_from_idle_tax_fund);
        $this->assertSame(0, (int) $retirement->outstanding_liability);

        $this->assertSame(300_000, (int) $burn->account->fresh()->balance_active);
        $this->assertSame(300_000, app(ActiveBaharReservationService::class)->availableActive($burn->account->fresh()) === 0
            ? (int) $reservation->fresh()->amount
            : -1,
            'The reserved 300,000 Gol must remain fully backed after retirement destruction.'
        );
        $this->assertSame(0, (int) $idle->account->fresh()->balance_active);
    }

    private function setFundActiveBalance($account, int $amount): void
    {
        $account->balance_active = $amount;
        $account->balance_faded = 0;
        $account->balance = $amount;
        $account->save();

        $sub = SubAccount::where('sub_account_code', $account->account_number)->firstOrFail();
        $sub->balance_active = $amount;
        $sub->balance_faded = 0;
        $sub->balance = $amount;
        $sub->save();
    }
}
