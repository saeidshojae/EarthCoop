<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\BalanceNormalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceNormalizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_and_apply_only_normalize_cached_totals(): void
    {
        $main = Account::create([
            'account_number' => '1000000001',
            'name' => 'Main',
            'type' => 'user',
            'balance' => 99999,
            'balance_active' => 400,
            'balance_faded' => 600,
            'status' => 1,
        ]);

        $sub = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => '1000000001-001',
            'name' => 'Sub',
            'balance' => 88888,
            'balance_active' => 100,
            'balance_faded' => 200,
            'status' => 1,
        ]);

        $service = app(BalanceNormalizationService::class);
        $plan = $service->plan();

        $this->assertSame(1, $plan['account_change_count']);
        $this->assertSame(1, $plan['subaccount_change_count']);

        // Planning is read-only.
        $this->assertSame(99999, (int) $main->fresh()->balance);
        $this->assertSame(88888, (int) $sub->fresh()->balance);

        $service->apply();

        $main->refresh();
        $sub->refresh();

        $this->assertSame(1000, (int) $main->balance);
        $this->assertSame(400, (int) $main->balance_active);
        $this->assertSame(600, (int) $main->balance_faded);

        $this->assertSame(300, (int) $sub->balance);
        $this->assertSame(100, (int) $sub->balance_active);
        $this->assertSame(200, (int) $sub->balance_faded);
    }
}
