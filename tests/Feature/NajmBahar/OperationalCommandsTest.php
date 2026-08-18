<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_idle_observation_command_never_charges_or_moves_active_money(): void
    {
        $user = User::factory()->create();
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $account->balance_active = 25_000;
        $account->balance = 25_000;
        $account->created_at = now()->subYear();
        $account->save();

        $this->artisan('najm-bahar:observe-idle-capital', ['--user' => $user->id, '--record' => true])
            ->assertSuccessful();

        $this->assertSame(25_000, (int) $account->fresh()->balance_active);
        $this->assertDatabaseHas('najm_bahar_idle_money_assessments', [
            'user_id' => $user->id,
            'active_balance_gol' => 25_000,
        ]);
    }

    public function test_retirement_liability_command_is_safe_when_nothing_is_outstanding(): void
    {
        $this->artisan('najm-bahar:settle-retirement-liabilities')
            ->expectsOutputToContain('Processed 0 liabilities')
            ->assertSuccessful();
    }
}
