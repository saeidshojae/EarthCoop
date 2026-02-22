<?php

namespace Tests\Feature\NajmHoda;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Fee;
use App\Modules\NajmBahar\Models\SalaryRun;
use App\Observers\NajmHoda\NajmBaharGenericModelObserver;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Tests\TestCase;

class NajmBaharGenericInstrumentationTest extends TestCase
{
    public function test_account_created_emits_generic_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $account = new Account([
            'account_number' => '1000000999',
            'user_id' => 51,
            'type' => 'user',
            'balance' => 10000,
            'status' => 'active',
        ]);
        $account->id = 301;

        (new NajmBaharGenericModelObserver())->created($account);

        $events = $bus->recent('najm_hoda.input.najm_bahar.account.created', 1);
        $this->assertNotEmpty($events);
        $this->assertSame(301, (int) data_get($events[0], 'payload.model_id'));
        $this->assertSame('economy:najm-bahar', (string) data_get($events[0], 'payload.scope'));
    }

    public function test_fee_status_change_emits_status_changed_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $fee = new Fee([
            'name' => 'fee1',
            'type' => 'fixed',
            'transaction_type' => 'all',
            'is_active' => false,
        ]);
        $fee->id = 401;
        $fee->syncOriginal();
        $fee->is_active = true;

        (new NajmBaharGenericModelObserver())->updated($fee);

        $events = $bus->recent('najm_hoda.input.najm_bahar.fee.status_changed', 1);
        $this->assertNotEmpty($events);
        $this->assertSame('is_active', (string) data_get($events[0], 'payload.field'));
        $this->assertSame(true, (bool) data_get($events[0], 'payload.to'));
    }

    public function test_salary_run_deleted_emits_generic_deleted_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $run = new SalaryRun([
            'status' => 'processing',
            'created_by' => 77,
        ]);
        $run->id = 901;

        (new NajmBaharGenericModelObserver())->deleted($run);

        $events = $bus->recent('najm_hoda.input.najm_bahar.salary_run.deleted', 1);
        $this->assertNotEmpty($events);
        $this->assertSame(901, (int) data_get($events[0], 'payload.model_id'));
    }
}

