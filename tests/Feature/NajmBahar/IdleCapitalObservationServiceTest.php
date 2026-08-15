<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\IdleCapitalObservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdleCapitalObservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_without_recent_external_active_outflow_is_only_marked_as_candidate(): void
    {
        $user = User::factory()->create();
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $account->balance_active = 20_000;
        $account->balance = 20_000;
        $account->created_at = now()->subYear();
        $account->save();

        $observation = app(IdleCapitalObservationService::class)
            ->observeUser($user->id, now());

        $this->assertTrue($observation['is_idle_candidate']);
        $this->assertSame(20_000, $observation['idle_candidate_gol']);
        $this->assertTrue($observation['assessment_only']);
        $this->assertSame(20_000, (int) $account->fresh()->balance_active, 'Observation must never move money.');
    }

    public function test_internal_active_transfer_does_not_reset_idle_clock_but_external_active_debit_does(): void
    {
        $user = User::factory()->create();
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $account->balance_active = 20_000;
        $account->balance = 20_000;
        $account->created_at = now()->subYear();
        $account->save();

        $internalTx = Transaction::create([
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => 1_000,
            'type' => 'immediate',
            'status' => 'completed',
            'metadata' => ['type' => 'internal_account_transfer', 'money_state' => 'active'],
            'description' => 'internal reshuffle',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        LedgerEntry::create([
            'transaction_id' => $internalTx->id,
            'account_id' => $account->id,
            'amount' => -1_000,
            'entry_type' => 'debit',
            'meta' => ['type' => 'internal_account_transfer', 'balance_bucket' => 'active'],
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $service = app(IdleCapitalObservationService::class);
        $this->assertTrue($service->observeUser($user->id, now())['is_idle_candidate']);

        $externalTx = Transaction::create([
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => 500,
            'type' => 'immediate',
            'status' => 'completed',
            'metadata' => ['type' => 'purchase', 'balance_type' => 'active'],
            'description' => 'external economic use marker',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        LedgerEntry::create([
            'transaction_id' => $externalTx->id,
            'account_id' => $account->id,
            'amount' => -500,
            'entry_type' => 'debit',
            'meta' => ['type' => 'purchase', 'balance_type' => 'active'],
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->assertFalse($service->observeUser($user->id, now())['is_idle_candidate']);
    }

    public function test_recording_observation_persists_policy_review_snapshot_without_tax_charge(): void
    {
        $user = User::factory()->create();
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $account->balance_active = 9_000;
        $account->balance = 9_000;
        $account->created_at = now()->subYear();
        $account->save();

        $assessment = app(IdleCapitalObservationService::class)->recordUser($user->id, now());

        $this->assertSame('idle_candidate', $assessment->status);
        $this->assertSame(9_000, (int) $assessment->taxable_candidate_gol);
        $this->assertTrue((bool) ($assessment->metadata['assessment_only'] ?? false));
        $this->assertFalse((bool) ($assessment->metadata['tax_charged'] ?? true));
        $this->assertSame(9_000, (int) $account->fresh()->balance_active);
    }
}
