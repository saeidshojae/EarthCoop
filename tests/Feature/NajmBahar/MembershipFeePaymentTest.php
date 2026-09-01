<?php

namespace Tests\Feature\NajmBahar;

use App\Helpers\BaharMoney;
use App\Http\Controllers\Admin\ReputationController;
use App\Models\ReputationRule;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFeePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_info_exposes_canonical_payment_source_capabilities_without_charging_money(): void
    {
        [$user, $account] = $this->memberWithCredit();
        $before = (int) $account->balance;

        $response = $this->actingAs($user)
            ->getJson(route('najm-bahar.membership-fee.info'))
            ->assertOk()
            ->assertJsonPath('default_payment_source', 'dim')
            ->assertJsonPath('payment_source_required', true)
            ->assertJsonPath('can_pay_from_dim', true)
            ->assertJsonPath('has_enough_balance', true)
            ->assertJsonStructure([
                'balance_dim', 'balance_active', 'wallet_total', 'sub_accounts', 'sub_account',
                'main_active_balance', 'create_subaccount_url', 'transfer_url', 'breakdown',
            ]);

        $this->assertSame(BaharMoney::toGolFromBahar(10_000), (int) $response->json('wallet_total'));
        $this->assertSame($before, (int) $account->fresh()->balance);
        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee')->count());
    }

    public function test_membership_payment_requires_explicit_dim_or_active_source(): void
    {
        [$user, $account] = $this->memberWithCredit();
        $before = (int) $account->balance;

        $this->actingAs($user)->from(route('najm-bahar.wallet'))
            ->post(route('najm-bahar.membership-fee.pay'), [])
            ->assertRedirect(route('najm-bahar.wallet'))->assertSessionHasErrors('payment_source');

        $this->assertSame($before, (int) $account->fresh()->balance);
        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee')->count());
        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee_activation')->count());
    }

    public function test_member_can_pay_annual_fee_from_dim_money_by_activating_exactly_the_fee(): void
    {
        [$user, $account] = $this->memberWithCredit();
        $fee = BaharMoney::toGolFromBahar(12);

        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'), ['payment_source' => 'dim'])
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

    public function test_successful_membership_fee_payment_awards_configured_participation_points_once_for_the_payment_year(): void
    {
        [$user] = $this->memberWithCredit();
        $paymentYear = now()->year;
        if (now()->lessThan($user->created_at->copy()->setYear($paymentYear))) $paymentYear--;

        ReputationRule::create([
            'key'=>'membership_fee_paid','label'=>'Membership fee paid','weight'=>12,'active'=>true,
            'daily_cap'=>null,'dimension'=>'participation','convertible'=>true,'repeat_policy'=>'once_per_context',
        ]);

        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'), ['payment_source'=>'dim'])
            ->assertRedirect(route('najm-bahar.dashboard'));

        $reward = UserPointTransaction::where('user_id',$user->id)->where('action','membership_fee_paid')->firstOrFail();
        $this->assertSame(12,(int)$reward->delta);
        $this->assertSame('participation',$reward->dimension);
        $this->assertTrue((bool)$reward->convertible);
        $this->assertSame($paymentYear,(int)($reward->metadata['payment_year'] ?? 0));

        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'), ['payment_source'=>'dim']);
        $this->assertSame(1, UserPointTransaction::where('user_id',$user->id)->where('action','membership_fee_paid')
            ->where('reference_id',$paymentYear)->count());
    }

    public function test_membership_fee_paid_is_bootstrapped_as_an_admin_managed_convertible_rule(): void
    {
        app(ReputationController::class)->index();
        $rule = ReputationRule::where('key','membership_fee_paid')->firstOrFail();
        $this->assertTrue((bool)$rule->active);
        $this->assertSame(12,(int)$rule->weight);
        $this->assertSame('participation',$rule->dimension);
        $this->assertTrue((bool)$rule->convertible);
        $this->assertSame('once_per_context',$rule->repeat_policy);
    }

    public function test_member_can_choose_to_pay_annual_fee_from_existing_active_money(): void
    {
        [$user, $account] = $this->memberWithCredit();
        $fee = BaharMoney::toGolFromBahar(12);
        app(MonetaryService::class)->activateDim($account,$fee,'test activation',['type'=>'test_activation'],
            'test-membership-active-'.$user->id,false);
        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'), ['payment_source'=>'active'])
            ->assertRedirect(route('najm-bahar.dashboard'));
        $account->refresh();
        $this->assertSame(BaharMoney::toGolFromBahar(10_000)-$fee,(int)$account->balance_faded);
        $this->assertSame(0,(int)$account->balance_active);
        $this->assertSame(BaharMoney::toGolFromBahar(10_000)-$fee,(int)$account->balance);
        $this->assertSame(0,Transaction::where('metadata->type','membership_fee_activation')->count());
        $this->assertTreasurySplit();
    }

    public function test_replaying_membership_payment_does_not_charge_twice(): void
    {
        [$user,$account]=$this->memberWithCredit();
        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'),['payment_source'=>'dim'])
            ->assertRedirect(route('najm-bahar.dashboard'));
        $balanceAfterFirst=(int)$account->fresh()->balance;
        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'),['payment_source'=>'dim']);
        $this->assertSame($balanceAfterFirst,(int)$account->fresh()->balance);
        $this->assertSame(3,Transaction::where('metadata->type','membership_fee')->count());
    }

    public function test_versioned_policy_controls_membership_allocation_and_is_recorded(): void
    {
        $policy=MonetaryPolicyVersion::create(['version'=>77,'status'=>'active','effective_from'=>now()->subMinute(),
            'parameters'=>['membership_fee_gol'=>BaharMoney::toGolFromBahar(12),'membership_operations_gol'=>BaharMoney::toGolFromBahar(5),
                'membership_insurance_gol'=>BaharMoney::toGolFromBahar(4),'membership_burn_gol'=>BaharMoney::toGolFromBahar(3)]]);
        [$user]=$this->memberWithCredit();
        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'),['payment_source'=>'dim'])
            ->assertRedirect(route('najm-bahar.dashboard'));
        $splits=Transaction::where('metadata->type','membership_fee')->get()->keyBy(fn($tx)=>$tx->metadata['split']??'');
        $this->assertSame(BaharMoney::toGolFromBahar(5),(int)$splits['operations_salary']->amount);
        $this->assertSame(BaharMoney::toGolFromBahar(4),(int)$splits['central_insurance']->amount);
        $this->assertSame(BaharMoney::toGolFromBahar(3),(int)$splits['money_destruction']->amount);
        $this->assertSame($policy->id,(int)($splits['operations_salary']->metadata['policy_version_id']??0));
    }

    private function memberWithCredit(): array
    {
        $user=User::factory()->create();
        $account=app(AccountService::class)->createMainAccountForUser($user->id,'Test member');
        app(MonetaryService::class)->issueMembershipCredit($account,$user->id);
        return [$user,$account->fresh()];
    }

    private function assertTreasurySplit(): void
    {
        $treasury=app(TreasuryService::class);
        $operations=$treasury->get(TreasuryService::OPERATIONS_SALARY)->account->fresh();
        $insurance=$treasury->get(TreasuryService::CENTRAL_INSURANCE)->account->fresh();
        $burn=$treasury->get(TreasuryService::MONEY_DESTRUCTION)->account->fresh();
        $this->assertSame(BaharMoney::toGolFromBahar(6),(int)$operations->balance_active);
        $this->assertSame(BaharMoney::toGolFromBahar(3),(int)$insurance->balance_active);
        $this->assertSame(BaharMoney::toGolFromBahar(3),(int)$burn->balance_active);
    }
}
