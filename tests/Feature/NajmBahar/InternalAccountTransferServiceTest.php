<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\InternalAccountTransferService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalAccountTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dim_money_remains_dim_when_moved_main_to_sub_and_back(): void
    {
        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);

        $sub = app(\App\Modules\NajmBahar\Services\SubAccountService::class)
            ->createSubAccount($main->id, 'Savings');

        $service = app(InternalAccountTransferService::class);
        $before = app(AccountBalanceService::class)->aggregate($main->fresh());

        $toSub = $service->mainToSub(
            $main,
            $sub,
            500,
            'faded',
            'Move dim to child',
            'internal-dim-to-sub'
        );

        $main->refresh();
        $sub->refresh();
        $afterToSub = app(AccountBalanceService::class)->aggregate($main);

        $this->assertSame($before['total'], $afterToSub['total']);
        $this->assertSame($before['dim'], $afterToSub['dim']);
        $this->assertSame(0, $afterToSub['active']);
        $this->assertSame(500, (int) $sub->balance_faded);
        $this->assertSame(0, (int) $sub->balance_active);
        $this->assertSame((int) $main->balance_active + (int) $main->balance_faded, (int) $main->balance);
        $this->assertSame(2, LedgerEntry::where('transaction_id', $toSub->id)->count());

        $back = $service->subToMain(
            $sub,
            $main,
            500,
            'faded',
            'Return dim to main',
            'internal-dim-back-main'
        );

        $main->refresh();
        $sub->refresh();
        $afterBack = app(AccountBalanceService::class)->aggregate($main);

        $this->assertSame($before['total'], $afterBack['total']);
        $this->assertSame($before['dim'], $afterBack['dim']);
        $this->assertSame(0, $afterBack['active']);
        $this->assertSame(0, (int) $sub->balance_faded);
        $this->assertSame(2, LedgerEntry::where('transaction_id', $back->id)->count());
    }

    public function test_active_money_remains_active_and_replay_is_idempotent(): void
    {
        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);
        app(MonetaryService::class)->activateDim($main, 800, 'Test activation', ['type' => 'test'], 'activate-800');

        $sub = app(\App\Modules\NajmBahar\Services\SubAccountService::class)
            ->createSubAccount($main->id, 'Active child');

        $service = app(InternalAccountTransferService::class);
        $transaction = $service->mainToSub(
            $main,
            $sub,
            300,
            'active',
            'Move active to child',
            'internal-active-to-sub'
        );

        $replay = $service->mainToSub(
            $main,
            $sub,
            300,
            'active',
            'Move active to child',
            'internal-active-to-sub'
        );

        $this->assertSame($transaction->id, $replay->id);
        $this->assertSame(300, (int) $sub->fresh()->balance_active);
        $this->assertSame(500, (int) $main->fresh()->balance_active);
    }
}
