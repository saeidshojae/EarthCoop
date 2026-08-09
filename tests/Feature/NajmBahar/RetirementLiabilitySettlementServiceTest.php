<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MembershipRetirementService;
use App\Modules\NajmBahar\Services\RetirementLiabilitySettlementService;
use App\Modules\NajmBahar\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetirementLiabilitySettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_liability_is_settled_later_without_touching_estate(): void
    {
        $user = User::factory()->create();
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $main->balance_faded = 200_000;
        $main->balance_active = 150_000;
        $main->balance = 350_000;
        $main->save();

        $retirement = app(MembershipRetirementService::class)->retire($user->id, 'death');
        $liability = $retirement->liability;
        $this->assertSame(800_000, (int) $liability->amount);
        $this->assertSame(150_000, (int) $main->fresh()->balance_active);

        $treasury = app(TreasuryService::class);
        $burn = $treasury->get(TreasuryService::MONEY_DESTRUCTION);
        $burn->account->balance_active = 500_000;
        $burn->account->balance = 500_000;
        $burn->account->save();
        $burnSub = SubAccount::where('sub_account_code', $burn->account->account_number)->firstOrFail();
        $burnSub->balance_active = 500_000;
        $burnSub->balance = 500_000;
        $burnSub->save();

        $tax = $treasury->get(TreasuryService::IDLE_TAX);
        $tax->account->balance_active = 300_000;
        $tax->account->balance = 300_000;
        $tax->account->save();
        $taxSub = SubAccount::where('sub_account_code', $tax->account->account_number)->firstOrFail();
        $taxSub->balance_active = 300_000;
        $taxSub->balance = 300_000;
        $taxSub->save();

        $settled = app(RetirementLiabilitySettlementService::class)->settle($liability->id);

        $this->assertSame(800_000, (int) $settled->settled_amount);
        $this->assertSame('settled', $settled->status);
        $this->assertSame('completed', $settled->retirement->status);
        $this->assertSame(0, (int) $settled->retirement->outstanding_liability);
        $this->assertSame(500_000, (int) $settled->retirement->active_destroyed_from_burn_fund);
        $this->assertSame(300_000, (int) $settled->retirement->active_destroyed_from_idle_tax_fund);
        $this->assertSame(150_000, (int) $main->fresh()->balance_active, 'Estate active wealth must remain untouched.');
    }

    public function test_partial_settlement_can_be_retried_after_more_treasury_liquidity_arrives(): void
    {
        $user = User::factory()->create();
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $main->balance_faded = 500_000;
        $main->balance = 500_000;
        $main->save();

        $retirement = app(MembershipRetirementService::class)->retire($user->id, 'exit');
        $liability = $retirement->liability;
        $this->assertSame(500_000, (int) $liability->amount);

        $treasury = app(TreasuryService::class);
        $burn = $treasury->get(TreasuryService::MONEY_DESTRUCTION);
        $burn->account->balance_active = 200_000;
        $burn->account->balance = 200_000;
        $burn->account->save();
        $burnSub = SubAccount::where('sub_account_code', $burn->account->account_number)->firstOrFail();
        $burnSub->balance_active = 200_000;
        $burnSub->balance = 200_000;
        $burnSub->save();

        $service = app(RetirementLiabilitySettlementService::class);
        $partial = $service->settle($liability->id);
        $this->assertSame(200_000, (int) $partial->settled_amount);
        $this->assertSame('outstanding', $partial->status);

        $tax = $treasury->get(TreasuryService::IDLE_TAX);
        $tax->account->balance_active = 300_000;
        $tax->account->balance = 300_000;
        $tax->account->save();
        $taxSub = SubAccount::where('sub_account_code', $tax->account->account_number)->firstOrFail();
        $taxSub->balance_active = 300_000;
        $taxSub->balance = 300_000;
        $taxSub->save();

        $final = $service->settle($liability->id);
        $this->assertSame(500_000, (int) $final->settled_amount);
        $this->assertSame('settled', $final->status);
        $this->assertSame(0, (int) $final->retirement->outstanding_liability);
    }
}
