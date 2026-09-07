<?php

namespace Tests\Feature\NajmBahar;

use App\Helpers\BaharMoney;
use App\Models\User;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFeePolicyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_payment_fails_closed_when_policy_split_exceeds_declared_fee(): void
    {
        MonetaryPolicyVersion::create([
            'version' => 88,
            'status' => 'active',
            'effective_from' => now()->subMinute(),
            'parameters' => [
                'membership_fee_gol' => BaharMoney::toGolFromBahar(12),
                'membership_operations_gol' => BaharMoney::toGolFromBahar(7),
                'membership_insurance_gol' => BaharMoney::toGolFromBahar(3),
                'membership_burn_gol' => BaharMoney::toGolFromBahar(3),
            ],
        ]);

        $user = User::factory()->create();
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Policy integrity member');
        app(MonetaryService::class)->issueMembershipCredit($account, $user->id);
        $account->refresh();

        $before = [
            'balance' => (int) $account->balance,
            'active' => (int) $account->balance_active,
            'dim' => (int) $account->balance_faded,
            'transactions' => Transaction::count(),
            'ledger' => LedgerEntry::count(),
        ];

        $this->actingAs($user)
            ->from(route('najm-bahar.wallet'))
            ->post(route('najm-bahar.membership-fee.pay'), ['payment_source' => 'dim'])
            ->assertRedirect(route('najm-bahar.wallet'))
            ->assertSessionHas('error');

        $account->refresh();

        $this->assertSame($before['balance'], (int) $account->balance);
        $this->assertSame($before['active'], (int) $account->balance_active);
        $this->assertSame($before['dim'], (int) $account->balance_faded);
        $this->assertSame($before['transactions'], Transaction::count());
        $this->assertSame($before['ledger'], LedgerEntry::count());
        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee')->count());
        $this->assertSame(0, Transaction::where('metadata->type', 'membership_fee_activation')->count());
    }
}
