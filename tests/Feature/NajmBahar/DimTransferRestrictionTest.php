<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('پول کمرنگ قابل انتقال');

        app(TransactionService::class)->transfer(
            $from->account_number,
            $to->account_number,
            10_000,
            'forbidden dim transfer',
            [],
            'dim-user-to-user-test',
            'faded'
        );
    }

    public function test_legacy_referral_dim_transfer_is_suppressed_without_moving_money(): void
    {
        $accounts = app(AccountService::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $from = $accounts->createMainAccountForUser($a->id, 'New member');
        $to = $accounts->createMainAccountForUser($b->id, 'Referrer');

        $from->balance_faded = 1_000_000;
        $from->balance = 1_000_000;
        $from->save();

        $tx = app(TransactionService::class)->transfer(
            $from->account_number,
            $to->account_number,
            1_000,
            'legacy referral',
            ['type' => 'referral_bonus'],
            'legacy-referral-suppression-test',
            'faded'
        );

        $this->assertSame(0, (int) $tx->amount);
        $this->assertSame(1_000_000, (int) $from->fresh()->balance_faded);
        $this->assertSame(0, (int) $to->fresh()->balance_faded);
        $this->assertSame('legacy_dim_transfer_suppressed', $tx->metadata['monetary_event'] ?? null);
    }
}
