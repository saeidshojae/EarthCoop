<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MembershipRetirementService;
use App\Modules\NajmBahar\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipRetirementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_retirement_removes_exact_constitutional_footprint_without_touching_active_wealth(): void
    {
        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Member');
        $main->balance_faded = 500_000;
        $main->balance_active = 200_000;
        $main->balance = 700_000;
        $main->save();

        $sub = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => $main->account_number . '-001',
            'name' => 'Sub',
            'balance_faded' => 200_000,
            'balance_active' => 500_000,
            'balance' => 700_000,
            'status' => 1,
        ]);
        $accounts->ensureSubAccountAccount($sub);

        $treasury = app(TreasuryService::class);
        $burn = $treasury->get(TreasuryService::MONEY_DESTRUCTION);
        $burn->account->balance_active = 300_000;
        $burn->account->balance = 300_000;
        $burn->account->save();
        $burnSub = SubAccount::where('sub_account_code', $burn->account->account_number)->firstOrFail();
        $burnSub->balance_active = 300_000;
        $burnSub->balance = 300_000;
        $burnSub->save();

        $before = app(AccountBalanceService::class)->aggregate($main);
        $this->assertSame(700_000, $before['dim']);
        $this->assertSame(700_000, $before['active']);

        $retirement = app(MembershipRetirementService::class)->retire($user->id, 'death');

        $after = app(AccountBalanceService::class)->aggregate($main->fresh());

        $this->assertSame(NajmBaharConstitution::initialMembershipGol(),
            (int) $retirement->dim_cancelled
            + (int) $retirement->active_destroyed_from_burn_fund
            + (int) $retirement->active_destroyed_from_idle_tax_fund
            + (int) $retirement->outstanding_liability
        );
        $this->assertSame(700_000, (int) $retirement->dim_cancelled);
        $this->assertSame(300_000, (int) $retirement->active_destroyed_from_burn_fund);
        $this->assertSame(0, (int) $retirement->outstanding_liability);
        $this->assertSame(0, $after['dim']);
        $this->assertSame(700_000, $after['active'], 'Member active wealth must remain untouched for the estate.');
    }

    public function test_shortage_becomes_system_liability_not_member_or_estate_debt(): void
    {
        $user = User::factory()->create();
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $main->balance_faded = 200_000;
        $main->balance_active = 150_000;
        $main->balance = 350_000;
        $main->save();

        $retirement = app(MembershipRetirementService::class)->retire($user->id, 'exit');

        $this->assertSame(200_000, (int) $retirement->dim_cancelled);
        $this->assertSame(800_000, (int) $retirement->outstanding_liability);
        $this->assertSame('liability_outstanding', $retirement->status);
        $this->assertNotNull($retirement->liability);
        $this->assertSame(800_000, (int) $retirement->liability->amount);
        $this->assertTrue((bool) ($retirement->liability->metadata['estate_not_liable'] ?? false));
        $this->assertSame(150_000, (int) $main->fresh()->balance_active);
    }

    public function test_retirement_is_idempotent(): void
    {
        $user = User::factory()->create();
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Member');
        $main->balance_faded = 1_000_000;
        $main->balance = 1_000_000;
        $main->save();

        $service = app(MembershipRetirementService::class);
        $first = $service->retire($user->id, 'removal');
        $second = $service->retire($user->id, 'removal');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(0, (int) $main->fresh()->balance_faded);
    }

    public function test_dim_above_constitutional_footprint_is_not_confiscated_by_retirement(): void
    {
        $user = User::factory()->create();
        $main = app(AccountService::class)->createMainAccountForUser($user->id, 'Legacy Member');
        $main->balance_faded = 1_200_000;
        $main->balance = 1_200_000;
        $main->save();

        $retirement = app(MembershipRetirementService::class)->retire($user->id, 'exit');

        $this->assertSame(1_000_000, (int) $retirement->dim_cancelled);
        $this->assertSame(200_000, (int) $main->fresh()->balance_faded);
        $this->assertSame(200_000, (int) ($retirement->metadata['dim_above_constitutional_footprint_preserved'] ?? 0));
    }
}
