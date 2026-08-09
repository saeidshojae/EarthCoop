<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\InternalSubAccountTransferService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalSubAccountTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_owner_transfer_is_double_entry_and_idempotent_without_changing_aggregate_wealth(): void
    {
        $user = User::factory()->create();
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);

        $subAccounts = app(SubAccountService::class);
        $from = $subAccounts->createSubAccount($main->id, 'From');
        $to = $subAccounts->createSubAccount($main->id, 'To');
        $subAccounts->transferToSubAccount($main->id, $from->id, 400, 'Seed source', 'faded');

        $before = app(AccountBalanceService::class)->aggregate($main->fresh());
        $service = app(InternalSubAccountTransferService::class);

        $first = $service->transfer(
            $from,
            $to,
            125,
            'faded',
            'Canonical child move',
            'faded-key-test'
        );
        $second = $service->transfer(
            $from->fresh(),
            $to->fresh(),
            125,
            'faded',
            'Canonical child move',
            'faded-key-test'
        );

        $after = app(AccountBalanceService::class)->aggregate($main->fresh());

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(2, LedgerEntry::where('transaction_id', $first->id)->count());
        $this->assertSame(275, (int) $from->fresh()->balance_faded);
        $this->assertSame(125, (int) $to->fresh()->balance_faded);
        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['dim'], $after['dim']);
        $this->assertSame('internal_sub_account_transfer_service', $first->metadata['routed_by'] ?? null);
    }
}
