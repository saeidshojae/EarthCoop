<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountInvariantService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_scheduled_transactions_command(): void
    {
        [$scheduled, $fromSubAccount, $toSubAccount] = $this->scheduledTransfer(
            sourceActive: 1000,
            executeAt: now()->subMinute(),
        );

        $this->artisan('najm-bahar:process-scheduled')
            ->assertExitCode(0);

        $scheduled->refresh();
        $this->assertSame(
            'processed',
            $scheduled->status,
            (string) ($scheduled->last_error ?? 'Scheduled transfer was not processed.')
        );
        $this->assertSame(1, (int) $scheduled->attempts);
        $this->assertNull($scheduled->last_error);

        $fromSubAccount->refresh();
        $toSubAccount->refresh();
        $this->assertSame(900, (int) $fromSubAccount->balance_active);
        $this->assertSame(100, (int) $toSubAccount->balance_active);

        $transaction = NajmTransaction::findOrFail($scheduled->transaction_id);
        $this->assertSame('completed', $transaction->status);
        $this->assertSame(2, $transaction->ledgerEntries()->count());
    }

    public function test_scheduled_transaction_not_due_yet(): void
    {
        [$scheduled, $fromSubAccount, $toSubAccount] = $this->scheduledTransfer(
            sourceActive: 1000,
            executeAt: now()->addDay(),
        );

        $this->artisan('najm-bahar:process-scheduled')
            ->expectsOutput('NajmBahar scheduled processing completed. Processed: 0')
            ->assertExitCode(0);

        $scheduled->refresh();
        $this->assertSame('scheduled', $scheduled->status);
        $this->assertSame(0, (int) $scheduled->attempts);

        $this->assertSame(1000, (int) $fromSubAccount->fresh()->balance_active);
        $this->assertSame(0, (int) $toSubAccount->fresh()->balance_active);
    }

    public function test_scheduled_transaction_failure_retry_records_diagnostic(): void
    {
        [$scheduled, $fromSubAccount, $toSubAccount] = $this->scheduledTransfer(
            sourceActive: 50,
            executeAt: now()->subMinute(),
        );

        $this->artisan('najm-bahar:process-scheduled')
            ->expectsOutput('NajmBahar scheduled processing completed. Processed: 0')
            ->assertExitCode(0);

        $scheduled->refresh();
        $this->assertSame('scheduled', $scheduled->status);
        $this->assertSame(1, (int) $scheduled->attempts);
        $this->assertNotNull($scheduled->last_error);
        $this->assertStringContainsString('Insufficient active funds', $scheduled->last_error);

        $this->assertSame(50, (int) $fromSubAccount->fresh()->balance_active);
        $this->assertSame(0, (int) $toSubAccount->fresh()->balance_active);
    }

    public function test_scheduled_transaction_max_attempts_marks_failed(): void
    {
        [$scheduled] = $this->scheduledTransfer(
            sourceActive: 50,
            executeAt: now()->subMinute(),
            attempts: 4,
        );

        $this->artisan('najm-bahar:process-scheduled')
            ->expectsOutput('NajmBahar scheduled processing completed. Processed: 0')
            ->assertExitCode(0);

        $scheduled->refresh();
        $this->assertSame('failed', $scheduled->status);
        $this->assertSame(5, (int) $scheduled->attempts);
        $this->assertNotNull($scheduled->last_error);

        $transaction = NajmTransaction::findOrFail($scheduled->transaction_id);
        $this->assertSame('failed', $transaction->status);
    }

    /**
     * @return array{ScheduledTransaction, SubAccount, SubAccount}
     */
    private function scheduledTransfer(int $sourceActive, $executeAt, int $attempts = 0): array
    {
        Setting::query()->updateOrCreate(['id' => 1], [
            'najm_bahar_user_threshold' => 1,
        ]);

        $fromUser = User::factory()->create(['email_verified_at' => now()]);
        $toUser = User::factory()->create(['email_verified_at' => now()]);

        $accounts = app(AccountService::class);
        $fromMain = $accounts->createMainAccountForUser($fromUser->id);
        $toMain = $accounts->createMainAccountForUser($toUser->id);

        $subAccounts = app(SubAccountService::class);
        $fromSubAccount = $subAccounts->createSubAccount($fromMain->id, 'Scheduled source');
        $toSubAccount = $subAccounts->createSubAccount($toMain->id, 'Scheduled destination');

        $fromSubAccount->balance_active = $sourceActive;
        $fromSubAccount->balance_faded = 0;
        $fromSubAccount->balance = $sourceActive;
        $fromSubAccount->save();
        app(AccountInvariantService::class)->reconcileSubAccountMirror($fromSubAccount->fresh());

        $transaction = NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => null,
            'amount' => 100,
            'type' => 'scheduled',
            'status' => 'pending',
            'scheduled_at' => $executeAt,
            'metadata' => [
                'domain' => 'scheduled_transfer_test',
            ],
        ]);

        $scheduled = ScheduledTransaction::create([
            'transaction_id' => $transaction->id,
            'execute_at' => $executeAt,
            'status' => 'scheduled',
            'attempts' => $attempts,
            'payload' => [
                'type' => 'subaccount_transfer',
                'from_sub_account_id' => $fromSubAccount->id,
                'to_sub_account_id' => $toSubAccount->id,
                'amount' => 100,
                'money_state' => 'active',
                'description' => 'Scheduled canonical Active-Bahar transfer',
                'metadata' => [
                    'actor_user_id' => $fromUser->id,
                    'money_state' => 'active',
                ],
            ],
        ]);

        return [$scheduled, $fromSubAccount, $toSubAccount];
    }
}
