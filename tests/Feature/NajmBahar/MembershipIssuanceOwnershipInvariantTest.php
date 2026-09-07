<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipIssuanceOwnershipInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_credit_cannot_be_issued_into_an_account_owned_by_another_member(): void
    {
        $member = User::factory()->create();
        $otherMember = User::factory()->create();

        $foreignAccount = Account::create([
            'account_number' => '9199999901',
            'user_id' => $otherMember->id,
            'name' => 'Foreign member account',
            'type' => 'user',
            'balance' => 0,
            'balance_active' => 0,
            'balance_faded' => 0,
        ]);

        $beforeTransactions = Transaction::count();
        $beforeLedgerEntries = LedgerEntry::count();

        try {
            app(MonetaryService::class)->issueMembershipCredit($foreignAccount, $member->id);
            $this->fail('Expected membership issuance ownership invariant to reject a foreign account.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('owned', strtolower($exception->getMessage()));
        }

        $foreignAccount->refresh();

        $this->assertSame(0, (int) $foreignAccount->balance);
        $this->assertSame(0, (int) $foreignAccount->balance_active);
        $this->assertSame(0, (int) $foreignAccount->balance_faded);
        $this->assertSame($beforeTransactions, Transaction::count());
        $this->assertSame($beforeLedgerEntries, LedgerEntry::count());
    }
}
