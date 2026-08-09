<?php

namespace Tests\Feature\NajmBahar;

use App\Helpers\BaharMoney;
use App\Models\User;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFeePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_pay_annual_fee_from_dim_money_by_activating_exactly_the_fee(): void
    {
        [$user, $account] = $this->memberWithCredit();
        $fee = BaharMoney::toGolFromBahar(12);

        $this->actingAs($user)
            ->post(route('najm-bahar.membership-fee.pay'), ['payment_source' => 'dim'])
            ->assertRedirect(route('najm-bahar.dashboard'));

        $account->refresh();
        $this->assertSame(BaharMoney::toGolFromBahar(10_000) - $fee, (int) $account->balance_faded);
        $this->assertSame(0, (int) $account->balance_active);
        $this->assertSame(BaharMoney::toGolFromBahar(10_000) - $fee, (int) $account->balance);

        $activation = Transaction::where('metadata->type', 'membership_fee_activation')->firstOrFail();
        $this->assertSame($fee, (int) $activation->amount);
        $this->assertSame(2, LedgerEntry::where('transaction_id', $activation->id)->count());

        $this->assertTreasurySplit();
    }

    public function test_member_can_choose_to_pay_annual_fee_from_existing_active_money(): void
    {
        [$user, $account] = $this->memberWithCredit();
        $fee = BaharMoney::toGolFromBahar(12);

        app(MonetaryService::class)->activateDim(
            $account,
            $fee,
            'test activation',
            ['type' => 'test_activation'],
            'test-membership-active-' . $user->id,
            false
        );

        $this->actingAs($user)
            ->post(route('najm-bahar.membership-fee.pay'), ['payment_source' => 'active'])
            ->assertRedirect(route('najm-bahar.dashboard'));

        $account->refresh();
        $this->assertSame(BaharMoney::toGolFromBahar(10_000) - $fee, (int) $account->balance_faded);
        $this->assertSame(0, (int) $account->balance_active);
        $this->assertSame(BaharMoney::toGolFromBahar(10_000) - $fee, (int) $account->balance);

        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee_activation')->count());
        $this->assertTreasurySplit();
    }

    public function test_replaying_membership_payment_does_not_charge_twice(): void
    {
        [$user, $account] = $this->memberWithCredit();

        $this->actingAs($user)
            ->post(route('najm-bahar.membership-fee.pay'), ['payment_source' => 'dim'])
            ->assertRedirect(route('najm-bahar.dashboard'));

        $balanceAfterFirst = (int) $account->fresh()->balance;

        $this->actingAs($user)
            ->post(route('najm-bahar.membership-fee.pay'), ['payment_source' => 'dim']);

        $this->assertSame($balanceAfterFirst, (int) $account->fresh()->balance);
        $this->assertSame(3, Transaction::where('metadata->type', 'membership_fee')->count());
    }

    private function memberWithCredit(): array
    {
        $user = User::factory()->create();
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Test member');
        app(MonetaryService::class)->issueMembershipCredit($account, $user->id);

        return [$user, $account->fresh()];
    }

    private function assertTreasurySplit(): void
    {
        $treasury = app(TreasuryService::class);
        $operations = $treasury->get(TreasuryService::OPERATIONS_SALARY)->account->fresh();
        $insurance = $treasury->get(TreasuryService::CENTRAL_INSURANCE)->account->fresh();
        $burn = $treasury->get(TreasuryService::MONEY_DESTRUCTION)->account->fresh();

        $this->assertSame(BaharMoney::toGolFromBahar(6), (int) $operations->balance_active);
        $this->assertSame(BaharMoney::toGolFromBahar(3), (int) $insurance->balance_active);
        $this->assertSame(BaharMoney::toGolFromBahar(3), (int) $burn->balance_active);
    }
}
