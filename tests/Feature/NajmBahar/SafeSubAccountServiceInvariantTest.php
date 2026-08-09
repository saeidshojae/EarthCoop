<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\SafeSubAccountService;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeSubAccountServiceInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_bound_subaccount_service_reconciles_child_mirror_after_internal_transfer(): void
    {
        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);

        $service = app(SubAccountService::class);
        $this->assertInstanceOf(SafeSubAccountService::class, $service);

        $sub = $service->createSubAccount($main->id, 'Savings');
        $mirror = Account::where('account_number', $sub->sub_account_code)->firstOrFail();
        $mirror->update([
            'balance' => 99,
            'balance_active' => 11,
            'balance_faded' => 88,
            'committed_dim' => 7,
        ]);

        $before = app(AccountBalanceService::class)->aggregate($main->fresh());

        $service->transferToSubAccount(
            $main->id,
            $sub->id,
            250,
            'Move dim through safe adapter',
            'faded'
        );

        $main->refresh();
        $sub->refresh();
        $mirror->refresh();
        $after = app(AccountBalanceService::class)->aggregate($main);

        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['dim'], $after['dim']);
        $this->assertSame((int) $sub->balance_active, (int) $mirror->balance_active);
        $this->assertSame((int) $sub->balance_faded, (int) $mirror->balance_faded);
        $this->assertSame((int) $sub->balance, (int) $mirror->balance);
        $this->assertSame(0, (int) $mirror->committed_dim);
    }
}
