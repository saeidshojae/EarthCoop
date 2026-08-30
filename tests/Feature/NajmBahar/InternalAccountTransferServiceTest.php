<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\NajmBahar\Services\DimCommitmentService;
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
        $this->assertSame(
            (int) $main->balance_active + (int) $main->balance_faded + (int) ($main->committed_dim ?? 0),
            (int) $main->balance
        );
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

    public function test_internal_active_transfer_preserves_committed_dim_in_main_local_total(): void
    {
        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Committed member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);

        app(DimCommitmentService::class)->commit(
            $main,
            400,
            'uat-internal-transfer-commitment',
            'Reserve committed Dim before internal transfer',
            ['type' => 'uat_internal_transfer']
        );
        app(MonetaryService::class)->activateDim(
            $main,
            800,
            'Activate spendable Dim before internal transfer',
            ['type' => 'uat_internal_transfer'],
            'uat-internal-transfer-activation',
            false
        );

        $sub = app(\App\Modules\NajmBahar\Services\SubAccountService::class)
            ->createSubAccount($main->id, 'Committed invariant child');

        $before = app(AccountBalanceService::class)->aggregate($main->fresh());

        app(InternalAccountTransferService::class)->mainToSub(
            $main,
            $sub,
            300,
            'active',
            'Move Active while committed Dim exists',
            'uat-internal-preserve-committed'
        );

        $main->refresh();
        $sub->refresh();
        $after = app(AccountBalanceService::class)->aggregate($main);

        $this->assertSame(400, (int) $main->committed_dim);
        $this->assertSame(
            (int) $main->balance_active + (int) $main->balance_faded + (int) $main->committed_dim,
            (int) $main->balance
        );
        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['dim'], $after['dim']);
        $this->assertSame($before['active'], $after['active']);
        $this->assertSame(300, (int) $sub->balance_active);
    }

    public function test_reserved_active_on_main_account_cannot_be_double_spent_into_subaccount(): void
    {
        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Reserved member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);
        app(MonetaryService::class)->activateDim($main, 800, 'Test activation', ['type' => 'test'], 'activate-reserved-800');

        $sub = app(\App\Modules\NajmBahar\Services\SubAccountService::class)
            ->createSubAccount($main->id, 'Reserved child');

        app(ActiveBaharReservationService::class)->reserve(
            $main->account_number,
            600,
            'reservation:internal-double-spend',
            'uat',
            1
        );

        $beforeMain = $main->fresh();
        $beforeSub = $sub->fresh();
        $beforeLedger = LedgerEntry::count();

        try {
            app(InternalAccountTransferService::class)->mainToSub(
                $main,
                $sub,
                300,
                'active',
                'Must not spend reserved Active',
                'internal-reserved-double-spend'
            );
            $this->fail('Expected reserved Active protection to reject the transfer.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Insufficient active funds', $exception->getMessage());
        }

        $this->assertSame((int) $beforeMain->balance_active, (int) $main->fresh()->balance_active);
        $this->assertSame((int) $beforeSub->balance_active, (int) $sub->fresh()->balance_active);
        $this->assertSame($beforeLedger, LedgerEntry::count());
        $this->assertSame(200, app(ActiveBaharReservationService::class)->availableActive($main->fresh()));
    }
}
