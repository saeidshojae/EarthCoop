<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\SafeSubAccountService;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeSubAccountBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_binding_uses_safe_adapter_and_faded_sub_to_main_stays_faded(): void
    {
        $service = app(SubAccountService::class);
        $this->assertInstanceOf(SafeSubAccountService::class, $service);

        $user = User::factory()->create();
        $accounts = app(AccountService::class);
        $main = $accounts->createMainAccountForUser($user->id, 'Member');
        app(MonetaryService::class)->issueMembershipCredit($main, $user->id);

        $sub = $service->createSubAccount($main->id, 'Child');
        $service->transferToSubAccount($main->id, $sub->id, 700, 'Move dim out', 'faded');

        $main->refresh();
        $sub->refresh();
        $this->assertSame(700, (int) $sub->balance_faded);
        $this->assertSame(0, (int) $sub->balance_active);

        $service->transferFromSubAccount($sub->id, $main->id, 300, 'Return dim', 'faded');

        $main->refresh();
        $sub->refresh();
        $aggregate = app(AccountBalanceService::class)->aggregate($main);

        $this->assertSame(0, (int) $main->balance_active);
        $this->assertSame(0, (int) $sub->balance_active);
        $this->assertSame(400, (int) $sub->balance_faded);
        $this->assertSame(1_000_000, $aggregate['dim']);
        $this->assertSame(0, $aggregate['active']);
        $this->assertSame(1_000_000, $aggregate['total']);
    }
}
