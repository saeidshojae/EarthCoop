<?php

namespace Tests\Feature\NajmBahar;

use App\Helpers\BaharMoney;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialMembershipCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_najm_bahar_agreement_creates_exactly_ten_thousand_bahar_as_dim_money(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ]);

        $response->assertRedirect(route('najm-bahar.dashboard'));

        $account = Account::where('user_id', $user->id)
            ->where('type', 'user')
            ->firstOrFail();

        $expected = BaharMoney::toGolFromBahar(10_000);

        $this->assertSame(NajmBaharConstitution::initialMembershipGol(), $expected);
        $this->assertSame($expected, (int) $account->balance);
        $this->assertSame(0, (int) $account->balance_active);
        $this->assertSame($expected, (int) $account->balance_faded);

        $initialFunding = Transaction::where('to_account_id', $account->id)
            ->where('metadata->type', 'initial_funding')
            ->firstOrFail();

        $this->assertSame($expected, (int) $initialFunding->amount);
        $this->assertSame(0, (int) data_get($initialFunding->metadata, 'active_amount'));
        $this->assertSame($expected, (int) data_get($initialFunding->metadata, 'faded_amount'));
    }

    public function test_reopening_dashboard_does_not_issue_membership_credit_twice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ])->assertRedirect(route('najm-bahar.dashboard'));

        $account = Account::where('user_id', $user->id)
            ->where('type', 'user')
            ->firstOrFail();

        $before = (int) $account->balance;

        $this->actingAs($user)->get(route('najm-bahar.dashboard'))->assertOk();

        $account->refresh();

        $this->assertSame($before, (int) $account->balance);
        $this->assertSame(1, Transaction::where('to_account_id', $account->id)
            ->where('metadata->type', 'initial_funding')
            ->count());
    }
}
