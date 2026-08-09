<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DimTransferRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dim_cannot_be_transferred_between_users(): void
    {
        $accounts = app(AccountService::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $from = $accounts->createMainAccountForUser($a->id, 'A');
        $to = $accounts->createMainAccountForUser($b->id, 'B');

        $from->balance_faded = 100_000;
        $from->balance = 100_000;
        $from->save();

        try {
            app(TransactionService::class)->transfer(
                $from->account_number,
                $to->account_number,
                10_000,
                'forbidden dim transfer',
                [],
                'dim-user-to-user-test',
                'faded'
            );
            $this->fail('Expected person-to-person dim transfer to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('پول کمرنگ قابل انتقال', $exception->getMessage());
        }

        $this->assertSame(100_000, (int) $from->fresh()->balance_faded);
        $this->assertSame(0, (int) $to->fresh()->balance_faded);
        $this->assertSame(0, Transaction::where('metadata->idempotency_key', 'dim-user-to-user-test')->count());
    }

    public function test_legacy_referral_label_cannot_bypass_dim_transfer_prohibition(): void
    {
        $accounts = app(AccountService::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $from = $accounts->createMainAccountForUser($a->id, 'New member');
        $to = $accounts->createMainAccountForUser($b->id, 'Referrer');

        $from->balance_faded = 1_000_000;
        $from->balance = 1_000_000;
        $from->save();

        try {
            app(TransactionService::class)->transfer(
                $from->account_number,
                $to->account_number,
                1_000,
                'legacy referral',
                ['type' => 'referral_bonus'],
                'legacy-referral-must-be-rejected',
                'faded'
            );
            $this->fail('Legacy referral metadata must not bypass the constitutional dim restriction.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('پول کمرنگ قابل انتقال', $exception->getMessage());
        }

        $this->assertSame(1_000_000, (int) $from->fresh()->balance_faded);
        $this->assertSame(0, (int) $to->fresh()->balance_faded);
        $this->assertSame(0, Transaction::where('metadata->idempotency_key', 'legacy-referral-must-be-rejected')->count());
    }
}
