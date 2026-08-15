<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\ScheduledSubAccountTransferExecutor;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledSubAccountTransferExecutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_subaccount_execution_preserves_placeholder_identity_and_is_replay_safe(): void
    {
        $user = User::factory()->create();
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);
        app(MonetaryService::class)->activateDim($main, 500, 'Activate for scheduled test', ['type' => 'test'], 'scheduled-activate-500');

        $subAccounts = app(SubAccountService::class);
        $from = $subAccounts->createSubAccount($main->id, 'From');
        $to = $subAccounts->createSubAccount($main->id, 'To');
        $subAccounts->transferToSubAccount($main->id, $from->id, 300, 'Seed active source', 'active');

        $placeholder = NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => null,
            'amount' => 120,
            'type' => 'scheduled',
            'status' => 'pending',
            'scheduled_at' => now()->subMinute(),
        ]);

        $scheduled = ScheduledTransaction::create([
            'transaction_id' => $placeholder->id,
            'execute_at' => now()->subMinute(),
            'status' => 'scheduled',
            'attempts' => 0,
            'payload' => [
                'type' => 'subaccount_transfer',
                'from_sub_account_id' => $from->id,
                'to_sub_account_id' => $to->id,
                'amount' => 120,
                'money_state' => 'active',
                'description' => 'Scheduled active redistribution',
            ],
        ]);

        $executor = app(ScheduledSubAccountTransferExecutor::class);
        $first = $executor->execute($scheduled);

        $this->assertSame((int) $placeholder->id, (int) $first->id);
        $this->assertSame('completed', $first->status);
        $this->assertSame(2, LedgerEntry::where('transaction_id', $placeholder->id)->count());
        $this->assertSame(180, (int) $from->fresh()->balance_active);
        $this->assertSame(120, (int) $to->fresh()->balance_active);

        $second = $executor->execute($scheduled->fresh());

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(2, LedgerEntry::where('transaction_id', $placeholder->id)->count());
        $this->assertSame(180, (int) $from->fresh()->balance_active);
        $this->assertSame(120, (int) $to->fresh()->balance_active);
    }
}
