<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\SafeTransactionService;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeTransactionBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_service_binding_keeps_main_balance_local_for_own_subaccount_transfer(): void
    {
        $transactions = app(TransactionService::class);
        $this->assertInstanceOf(SafeTransactionService::class, $transactions);

        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);
        app(MonetaryService::class)->activateDim($main, 1_000, 'activate', ['type' => 'test'], 'safe-tx-activate');

        $sub = app(SubAccountService::class)->createSubAccount($main->id, 'Child');
        $accounts->ensureSubAccountAccount($sub);

        $transactions->transfer(
            $main->account_number,
            $sub->sub_account_code,
            250,
            'Own internal move',
            ['type' => 'test_internal'],
            'safe-tx-own-main-sub',
            'active',
            'internal'
        );

        $main->refresh();
        $sub->refresh();
        $aggregate = app(AccountBalanceService::class)->aggregate($main);

        $this->assertSame(750, (int) $main->balance_active);
        $this->assertSame(250, (int) $sub->balance_active);
        $this->assertSame((int) $main->balance_active + (int) $main->balance_faded, (int) $main->balance);
        $this->assertSame(1_000_000, $aggregate['total']);
        $this->assertSame(1_000, $aggregate['active']);
    }
}
