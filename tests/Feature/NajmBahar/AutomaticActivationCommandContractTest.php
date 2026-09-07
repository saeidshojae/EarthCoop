<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AutomaticActivationCommandContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_periodic_activation_is_policy_versioned_partial_supply_conserving_and_replay_safe(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

        $policy = MonetaryPolicyVersion::create([
            'version' => 7,
            'status' => 'active',
            'parameters' => [
                'auto_activation_enabled' => true,
                'auto_activation_period' => 'monthly',
                'auto_activation_amount_gol' => 1_000,
            ],
            'effective_from' => now()->subDay(),
        ]);

        $fullUser = User::factory()->create();
        $partialUser = User::factory()->create();

        $full = Account::create([
            'account_number' => '9300000001',
            'user_id' => $fullUser->id,
            'name' => 'Automatic activation full account',
            'type' => 'user',
            'balance' => 2_000,
            'balance_active' => 0,
            'balance_faded' => 2_000,
        ]);

        $partial = Account::create([
            'account_number' => '9300000002',
            'user_id' => $partialUser->id,
            'name' => 'Automatic activation partial account',
            'type' => 'user',
            'balance' => 600,
            'balance_active' => 0,
            'balance_faded' => 600,
        ]);

        $this->artisan('najm-bahar:activate-faded')
            ->assertExitCode(Command::SUCCESS);

        $full->refresh();
        $partial->refresh();

        $this->assertSame(2_000, (int) $full->balance);
        $this->assertSame(1_000, (int) $full->balance_active);
        $this->assertSame(1_000, (int) $full->balance_faded);

        $this->assertSame(600, (int) $partial->balance);
        $this->assertSame(600, (int) $partial->balance_active);
        $this->assertSame(0, (int) $partial->balance_faded);

        $periodKey = '2026-08';
        $fullKey = 'auto-activation-' . $periodKey . '-account-' . $full->id;
        $partialKey = 'auto-activation-' . $periodKey . '-account-' . $partial->id;

        $fullTransaction = Transaction::where('metadata->idempotency_key', $fullKey)->firstOrFail();
        $partialTransaction = Transaction::where('metadata->idempotency_key', $partialKey)->firstOrFail();

        $this->assertSame($policy->id, (int) data_get($fullTransaction->metadata, 'policy_version_id'));
        $this->assertSame(7, (int) data_get($fullTransaction->metadata, 'policy_version'));
        $this->assertSame('2026-08', data_get($fullTransaction->metadata, 'period_key'));
        $this->assertSame(1_000, (int) $fullTransaction->amount);
        $this->assertSame(600, (int) $partialTransaction->amount);

        $this->artisan('najm-bahar:activate-faded')
            ->assertExitCode(Command::SUCCESS);

        $full->refresh();
        $partial->refresh();

        $this->assertSame(1_000, (int) $full->balance_active);
        $this->assertSame(1_000, (int) $full->balance_faded);
        $this->assertSame(600, (int) $partial->balance_active);
        $this->assertSame(0, (int) $partial->balance_faded);
        $this->assertSame(1, Transaction::where('metadata->idempotency_key', $fullKey)->count());
        $this->assertSame(1, Transaction::where('metadata->idempotency_key', $partialKey)->count());
    }
}
