<?php

namespace Tests\Feature\NajmHoda;

use App\Modules\NajmBahar\Models\Investment;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction;
use App\Observers\NajmHoda\NajmBaharInvestmentObserver;
use App\Observers\NajmHoda\NajmBaharScheduledTransactionObserver;
use App\Observers\NajmHoda\NajmBaharTransactionObserver;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Tests\TestCase;

class NajmBaharInstrumentationTest extends TestCase
{
    public function test_transaction_created_emits_najm_bahar_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $transaction = new Transaction([
            'tracking_number' => 'TRX-20260221-00001',
            'from_account_id' => 10,
            'to_account_id' => 11,
            'amount' => 5000,
            'type' => 'immediate',
            'status' => 'completed',
        ]);
        $transaction->id = 9001;

        (new NajmBaharTransactionObserver())->created($transaction);

        $events = $bus->recent('najm_hoda.input.najm_bahar.transaction.created', 1);
        $this->assertNotEmpty($events);
        $this->assertSame(9001, (int) data_get($events[0], 'payload.transaction_id'));
        $this->assertSame('economy:najm-bahar', (string) data_get($events[0], 'payload.scope'));
    }

    public function test_transaction_status_changed_emits_najm_bahar_status_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $transaction = new Transaction([
            'tracking_number' => 'TRX-20260221-00002',
            'status' => 'pending',
        ]);
        $transaction->id = 9002;
        $transaction->syncOriginal();
        $transaction->status = 'completed';

        (new NajmBaharTransactionObserver())->updated($transaction);

        $events = $bus->recent('najm_hoda.input.najm_bahar.transaction.status_changed', 1);
        $this->assertNotEmpty($events);
        $this->assertSame('pending', (string) data_get($events[0], 'payload.from_status'));
        $this->assertSame('completed', (string) data_get($events[0], 'payload.to_status'));
    }

    public function test_scheduled_transaction_and_investment_created_emit_events(): void
    {
        $bus = new InMemoryRuntimeEventBus(120);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $scheduled = new ScheduledTransaction([
            'transaction_id' => 9002,
            'status' => 'pending',
            'attempts' => 0,
        ]);
        $scheduled->id = 8001;
        (new NajmBaharScheduledTransactionObserver())->created($scheduled);

        $investment = new Investment([
            'project_id' => 101,
            'transaction_id' => 9002,
            'investor_type' => 'user',
            'investor_id' => 55,
            'amount' => 120000,
            'status' => 'pending',
        ]);
        $investment->id = 7001;
        (new NajmBaharInvestmentObserver())->created($investment);

        $scheduledEvents = $bus->recent('najm_hoda.input.najm_bahar.scheduled_transaction.created', 1);
        $investmentEvents = $bus->recent('najm_hoda.input.najm_bahar.investment.created', 1);

        $this->assertNotEmpty($scheduledEvents);
        $this->assertNotEmpty($investmentEvents);
        $this->assertSame(8001, (int) data_get($scheduledEvents[0], 'payload.scheduled_transaction_id'));
        $this->assertSame(7001, (int) data_get($investmentEvents[0], 'payload.investment_id'));
    }
}

