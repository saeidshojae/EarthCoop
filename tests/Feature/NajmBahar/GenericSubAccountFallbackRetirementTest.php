<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenericSubAccountFallbackRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_transaction_service_subaccount_to_subaccount_call_fails_closed_without_mutation(): void
    {
        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);

        $subAccounts = app(SubAccountService::class);
        $from = $subAccounts->createSubAccount($main->id, 'From');
        $to = $subAccounts->createSubAccount($main->id, 'To');
        $subAccounts->transferToSubAccount($main->id, $from->id, 200, 'Seed source', 'faded');

        $fromMirror = Account::where('account_number', $from->sub_account_code)->firstOrFail();
        $toMirror = Account::where('account_number', $to->sub_account_code)->firstOrFail();
        $fromBefore = $from->fresh()->toArray();
        $toBefore = $to->fresh()->toArray();

        try {
            app(TransactionService::class)->transfer(
                $fromMirror->account_number,
                $toMirror->account_number,
                50,
                'Forbidden generic child transfer',
                [],
                'release-d-generic-subaccount-fallback',
                'active'
            );
            $this->fail('Generic TransactionService unexpectedly executed a SubAccount ↔ SubAccount transfer.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('canonical SubAccountService boundary', $exception->getMessage());
        }

        $this->assertSame((int) $fromBefore['balance'], (int) $from->fresh()->balance);
        $this->assertSame((int) $fromBefore['balance_active'], (int) $from->fresh()->balance_active);
        $this->assertSame((int) $fromBefore['balance_faded'], (int) $from->fresh()->balance_faded);
        $this->assertSame((int) $toBefore['balance'], (int) $to->fresh()->balance);
        $this->assertSame((int) $toBefore['balance_active'], (int) $to->fresh()->balance_active);
        $this->assertSame((int) $toBefore['balance_faded'], (int) $to->fresh()->balance_faded);
    }
}
