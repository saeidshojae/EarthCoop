<?php

namespace Tests\Feature\NajmHoda;

use App\Models\User;
use App\Modules\NajmBahar\Models\Investment;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Services\InvestmentService;
use App\Modules\NajmBahar\Services\InvestmentTransferService;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Tests\TestCase;

class NajmBaharServiceHooksTest extends TestCase
{
    public function test_create_investment_rejected_event_is_emitted_for_non_approved_project(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $service = new InvestmentService(app(InvestmentTransferService::class));
        $project = new Project([
            'status' => 'draft',
            'required_capital' => 100000,
        ]);
        $project->id = 10;

        $investor = new User();
        $investor->id = 20;

        try {
            $service->createInvestment($project, $investor, 1000);
            $this->fail('Expected exception was not thrown.');
        } catch (\Exception) {
            $events = $bus->recent('najm_hoda.input.najm_bahar.service.investment.create.rejected', 1);
            $this->assertNotEmpty($events);
            $this->assertSame('project_not_approved', (string) data_get($events[0], 'payload.reason'));
        }
    }

    public function test_process_payment_rejected_event_is_emitted_when_investment_not_pending(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $service = new InvestmentService(app(InvestmentTransferService::class));
        $investment = new Investment([
            'status' => 'paid',
            'investor_type' => User::class,
            'investor_id' => 5,
        ]);
        $investment->id = 11;

        $payer = new User();
        $payer->id = 5;

        try {
            $service->processInvestmentPayment($investment, $payer);
            $this->fail('Expected exception was not thrown.');
        } catch (\Exception) {
            $events = $bus->recent('najm_hoda.input.najm_bahar.service.investment.process_payment.rejected', 1);
            $this->assertNotEmpty($events);
            $this->assertSame('investment_not_pending', (string) data_get($events[0], 'payload.reason'));
        }
    }
}
