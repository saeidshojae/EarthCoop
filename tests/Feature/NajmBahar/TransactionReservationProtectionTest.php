<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\NajmBahar\Services\InternalAccountTransferService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionReservationProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_active_on_subaccount_cannot_be_double_spent_through_generic_transfer(): void
    {
        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);
        app(MonetaryService::class)->activateDim($main, 800, 'UAT activation', ['type' => 'uat'], 'uat-generic-reservation-activation');

        $sub = app(SubAccountService::class)->createSubAccount($main->id, 'Spendable child');
        app(InternalAccountTransferService::class)->mainToSub(
            $main,
            $sub,
            500,
            'active',
            'Fund child',
            'uat-fund-child-before-reservation'
        );

        $mirror = Account::where('account_number', $sub->sub_account_code)->firstOrFail();
        $system = Account::create([
            'account_number' => '0000000999',
            'name' => 'UAT system destination',
            'type' => 'system',
            'balance' => 0,
            'balance_active' => 0,
            'balance_faded' => 0,
            'status' => 1,
        ]);

        app(ActiveBaharReservationService::class)->reserve(
            $mirror->account_number,
            400,
            'reservation:generic-double-spend',
            'uat',
            2
        );

        $beforeMirror = $mirror->fresh();
        $beforeSub = $sub->fresh();
        $beforeMain = $main->fresh();
        $beforeSystem = $system->fresh();
        $beforeLedger = LedgerEntry::count();

        try {
            app(TransactionService::class)->transfer(
                $mirror->account_number,
                $system->account_number,
                200,
                'Must not spend reserved Active',
                ['system_operation' => true, 'type' => 'uat_reserved_double_spend'],
                'uat-reserved-generic-transfer',
                'active'
            );
            $this->fail('Expected generic transfer to reject spending reserved Active Bahar.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Insufficient active funds', $exception->getMessage());
        }

        $this->assertSame((int) $beforeMirror->balance_active, (int) $mirror->fresh()->balance_active);
        $this->assertSame((int) $beforeSub->balance_active, (int) $sub->fresh()->balance_active);
        $this->assertSame((int) $beforeMain->balance, (int) $main->fresh()->balance);
        $this->assertSame((int) $beforeSystem->balance_active, (int) $system->fresh()->balance_active);
        $this->assertSame($beforeLedger, LedgerEntry::count());
        $this->assertSame(100, app(ActiveBaharReservationService::class)->availableActive($mirror->fresh()));
    }
}
