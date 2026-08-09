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

    public function test_dim_can_move_between_subaccounts_of_same_owner_without_changing_aggregate_wealth(): void
    {
        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);

        $service = app(SubAccountService::class);
        $from = $service->createSubAccount($main->id, 'From');
        $to = $service->createSubAccount($main->id, 'To');
        $service->transferToSubAccount($main->id, $from->id, 400, 'Seed child', 'faded');

        $before = app(AccountBalanceService::class)->aggregate($main->fresh());
        $service->transferBetweenSubAccounts($from->id, $to->id, 150, 'Internal child move', 'faded');
        $after = app(AccountBalanceService::class)->aggregate($main->fresh());

        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['dim'], $after['dim']);
        $this->assertSame(250, (int) $from->fresh()->balance_faded);
        $this->assertSame(150, (int) $to->fresh()->balance_faded);
        $this->assertSame(
            (int) $from->fresh()->balance_faded,
            (int) Account::where('account_number', $from->sub_account_code)->value('balance_faded')
        );
        $this->assertSame(
            (int) $to->fresh()->balance_faded,
            (int) Account::where('account_number', $to->sub_account_code)->value('balance_faded')
        );
    }

    public function test_dim_transfer_between_subaccounts_of_independent_owners_is_rejected_without_mutation(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $accounts = app(AccountService::class);
        $firstMain = $accounts->createMainAccountForUser($firstUser->id, 'First');
        $secondMain = $accounts->createMainAccountForUser($secondUser->id, 'Second');
        app(MonetaryService::class)->issueMembershipCredit($firstMain, $firstUser->id);
        app(MonetaryService::class)->issueMembershipCredit($secondMain, $secondUser->id);

        $service = app(SubAccountService::class);
        $from = $service->createSubAccount($firstMain->id, 'First child');
        $to = $service->createSubAccount($secondMain->id, 'Second child');
        $service->transferToSubAccount($firstMain->id, $from->id, 300, 'Seed source', 'faded');

        $fromBefore = $from->fresh()->toArray();
        $toBefore = $to->fresh()->toArray();

        try {
            $service->transferBetweenSubAccounts($from->id, $to->id, 100, 'Forbidden dim transfer', 'faded');
            $this->fail('Inter-owner Dim transfer unexpectedly succeeded.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('پول کمرنگ', $exception->getMessage());
        }

        $this->assertSame((int) $fromBefore['balance_faded'], (int) $from->fresh()->balance_faded);
        $this->assertSame((int) $toBefore['balance_faded'], (int) $to->fresh()->balance_faded);
    }
}
